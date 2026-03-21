<?php

namespace Tests\Unit\Tests;

use App\Models\Site;
use App\Services\Whois\WhoisClientInterface;
use App\Tests\DomainTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainTestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Создать WHOIS-ответ с заданными датами.
     */
    protected function fakeWhoisResponse(
        ?string $creationDate = null,
        ?string $expiryDate = null,
        ?string $registrar = null,
    ): string {
        $lines = [];

        if ($creationDate) {
            $lines[] = "Creation Date: {$creationDate}";
        }

        if ($expiryDate) {
            $lines[] = "Registry Expiry Date: {$expiryDate}";
        }

        if ($registrar) {
            $lines[] = "Registrar: {$registrar}";
        }

        $lines[] = 'Domain Name: example.com';

        return implode("\n", $lines);
    }

    /**
     * Подготовить мок WhoisClientInterface.
     */
    protected function mockWhoisClient(?string $response): void
    {
        $mock = $this->createMock(WhoisClientInterface::class);
        $mock->method('query')->willReturn($response);
        $this->app->instance(WhoisClientInterface::class, $mock);
    }

    public function test_old_domain_returns_success(): void
    {
        $creationDate = date('Y-m-d', strtotime('-100 days'));
        $expiryDate = date('Y-m-d', strtotime('+265 days'));

        $this->mockWhoisClient($this->fakeWhoisResponse($creationDate, $expiryDate, 'Example Registrar'));

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(DomainTest::class);
        $result = $test->run($site);

        $this->assertEquals('success', $result->status);
        $this->assertEquals('example.com', $result->value['domain']);
        $this->assertGreaterThan(20, $result->value['days_since_registration']);
    }

    public function test_young_domain_returns_failed(): void
    {
        $creationDate = date('Y-m-d', strtotime('-5 days'));
        $expiryDate = date('Y-m-d', strtotime('+360 days'));

        $this->mockWhoisClient($this->fakeWhoisResponse($creationDate, $expiryDate, 'Example Registrar'));

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(DomainTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertLessThanOrEqual(20, $result->value['days_since_registration']);
    }

    public function test_domain_expiring_soon_returns_warning(): void
    {
        $creationDate = date('Y-m-d', strtotime('-500 days'));
        $expiryDate = date('Y-m-d', strtotime('+15 days'));

        $this->mockWhoisClient($this->fakeWhoisResponse($creationDate, $expiryDate, 'Example Registrar'));

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(DomainTest::class);
        $result = $test->run($site);

        $this->assertEquals('warning', $result->status);
        $this->assertLessThanOrEqual(30, $result->value['days_until_expiry']);
    }

    public function test_extracts_registration_date(): void
    {
        $this->mockWhoisClient($this->fakeWhoisResponse('2020-01-15T00:00:00Z', '2027-01-15T00:00:00Z'));

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(DomainTest::class);
        $result = $test->run($site);

        $this->assertNotNull($result->value['registered_at']);
        $this->assertStringContains('2020-01-15', $result->value['registered_at']);
    }

    public function test_extracts_expiration_date(): void
    {
        $this->mockWhoisClient($this->fakeWhoisResponse('2020-01-15T00:00:00Z', '2027-01-15T00:00:00Z'));

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(DomainTest::class);
        $result = $test->run($site);

        $this->assertNotNull($result->value['expires_at']);
        $this->assertStringContains('2027-01-15', $result->value['expires_at']);
    }

    public function test_extracts_registrar(): void
    {
        $this->mockWhoisClient($this->fakeWhoisResponse('2020-01-15T00:00:00Z', '2027-01-15T00:00:00Z', 'Example Registrar Inc.'));

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(DomainTest::class);
        $result = $test->run($site);

        $this->assertEquals('Example Registrar Inc.', $result->value['registrar']);
    }

    public function test_invalid_url_returns_failed(): void
    {
        $site = Site::factory()->createQuietly(['url' => 'not-a-valid-url']);
        $test = $this->app->make(DomainTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('Не удалось извлечь домен', $result->message);
    }

    public function test_whois_unavailable_returns_failed(): void
    {
        $this->mockWhoisClient(null);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(DomainTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('Не удалось получить данные WHOIS', $result->message);
    }

    public function test_strips_www_prefix(): void
    {
        $creationDate = date('Y-m-d', strtotime('-100 days'));
        $expiryDate = date('Y-m-d', strtotime('+265 days'));

        $mock = $this->createMock(WhoisClientInterface::class);
        $mock->expects($this->once())
            ->method('query')
            ->with('example.com')
            ->willReturn($this->fakeWhoisResponse($creationDate, $expiryDate));
        $this->app->instance(WhoisClientInterface::class, $mock);

        $site = Site::factory()->createQuietly(['url' => 'https://www.example.com']);
        $test = $this->app->make(DomainTest::class);
        $test->run($site);
    }

    public function test_metadata(): void
    {
        $test = $this->app->make(DomainTest::class);

        $this->assertEquals('domain', $test->getType());
        $this->assertEquals('Регистрация домена', $test->getName());
        $this->assertEquals(1440, $test->getDefaultInterval());
    }

    /**
     * Вспомогательный метод: проверка наличия подстроки.
     */
    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
