<?php

namespace Tests\Unit\Tests;

use App\Models\Site;
use App\Tests\AvailabilityTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvailabilityTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_response_returns_success(): void
    {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(AvailabilityTest::class);

        $result = $test->run($site);

        $this->assertEquals('success', $result->status);
        $this->assertEquals(200, $result->value['status_code']);
        $this->assertTrue($result->value['is_up']);
        $this->assertArrayHasKey('response_time_ms', $result->value);
    }

    public function test_redirect_response_returns_success(): void
    {
        Http::fake([
            '*' => Http::response('', 301),
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(AvailabilityTest::class);

        $result = $test->run($site);

        $this->assertEquals('success', $result->status);
        $this->assertEquals(301, $result->value['status_code']);
        $this->assertTrue($result->value['is_up']);
    }

    public function test_client_error_returns_failed(): void
    {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(AvailabilityTest::class);

        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertEquals(404, $result->value['status_code']);
        $this->assertFalse($result->value['is_up']);
    }

    public function test_server_error_returns_failed(): void
    {
        Http::fake([
            '*' => Http::response('Internal Server Error', 500),
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(AvailabilityTest::class);

        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertEquals(500, $result->value['status_code']);
        $this->assertFalse($result->value['is_up']);
    }

    public function test_connection_error_returns_failed(): void
    {
        Http::fake([
            '*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(AvailabilityTest::class);

        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertNull($result->value['status_code']);
        $this->assertFalse($result->value['is_up']);
        $this->assertEquals('connection_error', $result->value['error']);
    }

    public function test_metadata(): void
    {
        $test = $this->app->make(AvailabilityTest::class);

        $this->assertEquals('availability', $test->getType());
        $this->assertEquals('Доступность сайта', $test->getName());
        $this->assertEquals(5, $test->getDefaultInterval());
    }
}
