<?php

namespace Tests\Unit\Models;

use App\Models\Site;
use App\Models\SiteTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_interval_minutes_from_settings(): void
    {
        $site = Site::factory()->createQuietly();
        $siteTest = SiteTest::factory()->create([
            'site_id' => $site->id,
            'settings' => ['interval_minutes' => 10],
        ]);

        $this->assertEquals(10, $siteTest->getIntervalMinutes());
    }

    public function test_get_interval_minutes_defaults_when_no_settings(): void
    {
        $site = Site::factory()->createQuietly();
        $siteTest = SiteTest::factory()->create([
            'site_id' => $site->id,
            'settings' => null,
        ]);

        $this->assertEquals(60, $siteTest->getIntervalMinutes());
    }

    public function test_get_interval_minutes_defaults_when_empty_settings(): void
    {
        $site = Site::factory()->createQuietly();
        $siteTest = SiteTest::factory()->create([
            'site_id' => $site->id,
            'settings' => [],
        ]);

        $this->assertEquals(60, $siteTest->getIntervalMinutes());
    }

    public function test_belongs_to_site(): void
    {
        $site = Site::factory()->createQuietly();
        $siteTest = SiteTest::factory()->create(['site_id' => $site->id]);

        $this->assertTrue($siteTest->site->is($site));
    }
}
