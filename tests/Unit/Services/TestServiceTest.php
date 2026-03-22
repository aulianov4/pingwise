<?php

namespace Tests\Unit\Services;

use App\Models\Site;
use App\Models\SiteTest;
use App\Models\TestResult;
use App\Services\TestRegistry;
use App\Services\TestService;
use App\Tests\AvailabilityTest;
use App\Tests\DomainTest;
use App\Tests\SslTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_default_tests(): void
    {
        $registry = $this->app->make(TestRegistry::class);

        $tests = $registry->all();

        $this->assertCount(4, $tests);
        $this->assertArrayHasKey('availability', $tests);
        $this->assertArrayHasKey('ssl', $tests);
        $this->assertArrayHasKey('domain', $tests);
        $this->assertArrayHasKey('sitemap', $tests);
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

        $this->assertCount(4, $site->siteTests()->get());
        $this->assertNotNull($site->siteTests()->where('test_type', 'availability')->first());
        $this->assertNotNull($site->siteTests()->where('test_type', 'ssl')->first());
        $this->assertNotNull($site->siteTests()->where('test_type', 'domain')->first());
        $this->assertNotNull($site->siteTests()->where('test_type', 'sitemap')->first());
    }

    public function test_initialize_tests_for_site_does_not_duplicate(): void
    {
        $service = $this->app->make(TestService::class);

        $site = Site::factory()->createQuietly();
        $service->initializeTestsForSite($site);
        $service->initializeTestsForSite($site);

        $this->assertCount(4, $site->siteTests()->get());
    }
}
