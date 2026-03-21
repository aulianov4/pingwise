<?php

namespace Tests\Unit\Models;

use App\Models\Site;
use App\Models\TestResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_of_type_filters_by_test_type(): void
    {
        $site = Site::factory()->create();

        TestResult::factory()->create(['site_id' => $site->id, 'test_type' => 'ssl']);
        TestResult::factory()->create(['site_id' => $site->id, 'test_type' => 'availability']);
        TestResult::factory()->create(['site_id' => $site->id, 'test_type' => 'ssl']);

        $sslResults = TestResult::ofType('ssl')->get();

        $this->assertCount(2, $sslResults);
        $this->assertTrue($sslResults->every(fn ($r) => $r->test_type === 'ssl'));
    }

    public function test_scope_of_status_filters_by_status(): void
    {
        $site = Site::factory()->create();

        TestResult::factory()->create(['site_id' => $site->id, 'status' => 'success']);
        TestResult::factory()->create(['site_id' => $site->id, 'status' => 'failed']);
        TestResult::factory()->create(['site_id' => $site->id, 'status' => 'failed']);

        $failedResults = TestResult::ofStatus('failed')->get();

        $this->assertCount(2, $failedResults);
        $this->assertTrue($failedResults->every(fn ($r) => $r->status === 'failed'));
    }

    public function test_scope_for_period_week(): void
    {
        $site = Site::factory()->create();

        TestResult::factory()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subDays(3),
        ]);
        TestResult::factory()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subDays(10),
        ]);

        $weekResults = TestResult::forPeriod('week')->get();

        $this->assertCount(1, $weekResults);
    }

    public function test_scope_for_period_month(): void
    {
        $site = Site::factory()->create();

        TestResult::factory()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subDays(15),
        ]);
        TestResult::factory()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subDays(45),
        ]);

        $monthResults = TestResult::forPeriod('month')->get();

        $this->assertCount(1, $monthResults);
    }

    public function test_scope_latest_for_site_test(): void
    {
        $site = Site::factory()->create();

        TestResult::factory()->create([
            'site_id' => $site->id,
            'test_type' => 'availability',
            'checked_at' => now()->subHour(),
        ]);
        $latest = TestResult::factory()->create([
            'site_id' => $site->id,
            'test_type' => 'availability',
            'checked_at' => now(),
        ]);

        $result = TestResult::latestForSiteTest($site->id, 'availability')->first();

        $this->assertTrue($result->is($latest));
    }

    public function test_belongs_to_site(): void
    {
        $site = Site::factory()->create();
        $result = TestResult::factory()->create(['site_id' => $site->id]);

        $this->assertTrue($result->site->is($site));
    }
}
