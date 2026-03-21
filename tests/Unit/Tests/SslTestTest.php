<?php

namespace Tests\Unit\Tests;

use App\Models\Site;
use App\Services\Ssl\SslCheckerInterface;
use App\Tests\SslTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SslTestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Подготовить мок SslCheckerInterface.
     */
    protected function mockSslChecker(?array $certInfo): void
    {
        $mock = $this->createMock(SslCheckerInterface::class);
        $mock->method('getCertificateInfo')->willReturn($certInfo);
        $this->app->instance(SslCheckerInterface::class, $mock);
    }

    public function test_invalid_url_returns_failed(): void
    {
        $site = Site::factory()->createQuietly(['url' => 'not-a-valid-url']);
        $test = $this->app->make(SslTest::class);

        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('Не удалось извлечь домен', $result->message);
    }

    public function test_valid_certificate_returns_success(): void
    {
        $this->mockSslChecker([
            'issuer_cn' => 'Let\'s Encrypt Authority',
            'subject_cn' => 'example.com',
            'valid_to' => time() + (90 * 86400),
            'is_self_signed' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SslTest::class);
        $result = $test->run($site);

        $this->assertEquals('success', $result->status);
        $this->assertTrue($result->value['is_valid']);
        $this->assertFalse($result->value['is_self_signed']);
        $this->assertGreaterThan(30, $result->value['days_until_expiry']);
    }

    public function test_expiring_soon_returns_warning(): void
    {
        $this->mockSslChecker([
            'issuer_cn' => 'Let\'s Encrypt Authority',
            'subject_cn' => 'example.com',
            'valid_to' => time() + (15 * 86400),
            'is_self_signed' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SslTest::class);
        $result = $test->run($site);

        $this->assertEquals('warning', $result->status);
        $this->assertTrue($result->value['is_valid']);
        $this->assertLessThanOrEqual(30, $result->value['days_until_expiry']);
    }

    public function test_expired_certificate_returns_failed(): void
    {
        $this->mockSslChecker([
            'issuer_cn' => 'Let\'s Encrypt Authority',
            'subject_cn' => 'example.com',
            'valid_to' => time() - (5 * 86400),
            'is_self_signed' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SslTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertFalse($result->value['is_valid']);
    }

    public function test_self_signed_certificate_returns_failed(): void
    {
        $this->mockSslChecker([
            'issuer_cn' => 'example.com',
            'subject_cn' => 'example.com',
            'valid_to' => time() + (365 * 86400),
            'is_self_signed' => true,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SslTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertFalse($result->value['is_valid']);
        $this->assertTrue($result->value['is_self_signed']);
        $this->assertStringContainsString('самоподписанный', $result->message);
    }

    public function test_connection_failed_returns_failed(): void
    {
        $this->mockSslChecker(null);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SslTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertEquals('connection_failed', $result->value['error']);
    }

    public function test_metadata(): void
    {
        $test = $this->app->make(SslTest::class);

        $this->assertEquals('ssl', $test->getType());
        $this->assertEquals('SSL сертификат', $test->getName());
        $this->assertEquals(1440, $test->getDefaultInterval());
    }
}
