<?php

namespace Tests\Unit\Services;

use App\Events\TestStatusChanged;
use App\Models\Site;
use App\Models\SiteTest;
use App\Models\TestResult;
use App\Services\TestRegistry;
use App\Services\TestService;
use App\Tests\AvailabilityTest;
use App\Tests\DomainTest;
use App\Tests\SslTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_default_tests(): void
    {
        $registry = $this->app->make(TestRegistry::class);

        $tests = $registry->all();

        $this->assertCount(3, $tests);
        $this->assertArrayHasKey('availability', $tests);
        $this->assertArrayHasKey('ssl', $tests);
        $this->assertArrayHasKey('domain', $tests);
        $this->assertArrayNotHasKey('sitemap', $tests);
    }

    public function test_get_test_returns_instance_by_type(): void
    {
        $service = $this->app->make(TestService::class);

        $test = $service->getTest('availability');

        $this->assertInstanceOf(AvailabilityTest::class, $test);
    }

    public function test_get_test_returns_ssl_instance(): void
    {
        $service = $this->app->make(TestService::class);

        $test = $service->getTest('ssl');

        $this->assertInstanceOf(SslTest::class, $test);
    }

    public function test_get_test_returns_domain_instance(): void
    {
        $service = $this->app->make(TestService::class);

        $test = $service->getTest('domain');

        $this->assertInstanceOf(DomainTest::class, $test);
    }

    public function test_get_test_returns_null_for_unknown_type(): void
    {
        $service = $this->app->make(TestService::class);

        $test = $service->getTest('nonexistent');

        $this->assertNull($test);
    }

    public function test_should_run_test_returns_true_when_never_checked(): void
    {
        $service = $this->app->make(TestService::class);

        $site = Site::factory()->createQuietly();
        $siteTest = SiteTest::factory()->availability()->create([
            'site_id' => $site->id,
        ]);

        $this->assertTrue($service->shouldRunTest($site, 'availability', $siteTest));
    }

    public function test_should_run_test_returns_false_when_interval_not_elapsed(): void
    {
        $service = $this->app->make(TestService::class);

        $site = Site::factory()->createQuietly();
        $siteTest = SiteTest::factory()->availability()->create([
            'site_id' => $site->id,
            'settings' => ['interval_minutes' => 5],
        ]);

        TestResult::factory()->create([
            'site_id' => $site->id,
            'test_type' => 'availability',
            'checked_at' => now()->subMinute(),
        ]);

        $this->assertFalse($service->shouldRunTest($site, 'availability', $siteTest));
    }

    public function test_should_run_test_returns_true_when_interval_elapsed(): void
    {
        $service = $this->app->make(TestService::class);

        $site = Site::factory()->createQuietly();
        $siteTest = SiteTest::factory()->availability()->create([
            'site_id' => $site->id,
            'settings' => ['interval_minutes' => 5],
        ]);

        TestResult::factory()->create([
            'site_id' => $site->id,
            'test_type' => 'availability',
            'checked_at' => now()->subMinutes(10),
        ]);

        $this->assertTrue($service->shouldRunTest($site, 'availability', $siteTest));
    }

    public function test_should_run_test_returns_false_when_test_disabled(): void
    {
        $service = $this->app->make(TestService::class);

        $site = Site::factory()->createQuietly();
        $siteTest = SiteTest::factory()->availability()->disabled()->create([
            'site_id' => $site->id,
        ]);

        $this->assertFalse($service->shouldRunTest($site, 'availability', $siteTest));
    }

    public function test_initialize_tests_for_site_creates_all_test_types(): void
    {
        $service = $this->app->make(TestService::class);

        $site = Site::factory()->createQuietly();
        $service->initializeTestsForSite($site);

        $this->assertCount(3, $site->siteTests()->get());
        $this->assertNotNull($site->siteTests()->where('test_type', 'availability')->first());
        $this->assertNotNull($site->siteTests()->where('test_type', 'ssl')->first());
        $this->assertNotNull($site->siteTests()->where('test_type', 'domain')->first());
        $this->assertNull($site->siteTests()->where('test_type', 'sitemap')->first());
    }

    public function test_initialize_tests_for_site_does_not_duplicate(): void
    {
        $service = $this->app->make(TestService::class);

        $site = Site::factory()->createQuietly();
        $service->initializeTestsForSite($site);
        $service->initializeTestsForSite($site);

        $this->assertCount(3, $site->siteTests()->get());
    }

    public function test_run_test_returns_null_for_unregistered_sitemap(): void
    {
        $service = $this->app->make(TestService::class);

        $site = Site::factory()->createQuietly();
        SiteTest::factory()->sitemap()->create([
            'site_id' => $site->id,
            'is_enabled' => true,
        ]);

        $this->assertNull($service->runTest($site, 'sitemap'));
        $this->assertDatabaseMissing('test_results', [
            'site_id' => $site->id,
            'test_type' => 'sitemap',
        ]);
    }

    public function test_availability_does_not_dispatch_on_single_failure(): void
    {
        Event::fake([TestStatusChanged::class]);
        $this->app->forgetInstance(TestService::class);
        Http::fake([
            '*' => Http::response('err', 500),
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $result = $this->app->make(TestService::class)->runTest($site, 'availability');

        $this->assertFalse($result->value['is_up']);
        $this->assertFalse($result->value['incident_down']);
        $this->assertSame(1, $result->value['window_failures']);
        Event::assertNotDispatched(TestStatusChanged::class);
    }

    public function test_availability_dispatches_when_three_of_five_fail(): void
    {
        Event::fake([TestStatusChanged::class]);
        $this->app->forgetInstance(TestService::class);
        Http::fake([
            '*' => Http::response('err', 500),
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);

        foreach ([20, 10] as $minutesAgo) {
            TestResult::factory()->availability()->failed()->create([
                'site_id' => $site->id,
                'checked_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        $result = $this->app->make(TestService::class)->runTest($site, 'availability');

        $this->assertTrue($result->value['incident_down']);
        $this->assertSame(3, $result->value['window_failures']);
        Event::assertDispatched(TestStatusChanged::class);
    }

    public function test_availability_dispatches_recovery_when_failures_drop_below_three(): void
    {
        Event::fake([TestStatusChanged::class]);
        $this->app->forgetInstance(TestService::class);
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);

        foreach ([50, 40, 30, 20, 10] as $index => $minutesAgo) {
            $failed = $index < 3;
            TestResult::factory()->availability()->when($failed, fn ($factory) => $factory->failed())->create([
                'site_id' => $site->id,
                'checked_at' => now()->subMinutes($minutesAgo),
                'value' => [
                    'status_code' => $failed ? 500 : 200,
                    'response_time_ms' => 80,
                    'is_up' => ! $failed,
                    'incident_down' => true,
                ],
            ]);
        }

        $result = $this->app->make(TestService::class)->runTest($site, 'availability');

        $this->assertTrue($result->value['is_up']);
        $this->assertFalse($result->value['incident_down']);
        Event::assertDispatched(TestStatusChanged::class);
    }
}
