<?php

namespace Tests\Feature\Commands;

use App\Models\Site;
use App\Models\TestResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_deletes_results_older_than_one_year(): void
    {
        $site = Site::factory()->createQuietly();

        $oldResult = TestResult::factory()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subYears(2),
        ]);

        $freshResult = TestResult::factory()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subDay(),
        ]);

        $this->artisan('pingwise:cleanup')
            ->assertSuccessful();

        $this->assertDatabaseMissing('test_results', ['id' => $oldResult->id]);
        $this->assertDatabaseHas('test_results', ['id' => $freshResult->id]);
    }

    public function test_cleanup_respects_custom_days_option(): void
    {
        $site = Site::factory()->createQuietly();

        $oldResult = TestResult::factory()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subDays(40),
        ]);

        $freshResult = TestResult::factory()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subDays(20),
        ]);

        $this->artisan('pingwise:cleanup', ['--days' => 30])
            ->assertSuccessful();

        $this->assertDatabaseMissing('test_results', ['id' => $oldResult->id]);
        $this->assertDatabaseHas('test_results', ['id' => $freshResult->id]);
    }

    public function test_cleanup_with_no_old_results_deletes_nothing(): void
    {
        $site = Site::factory()->createQuietly();

        TestResult::factory()->create([
            'site_id' => $site->id,
            'checked_at' => now(),
        ]);

        $this->artisan('pingwise:cleanup')
            ->expectsOutputToContain('Удалено записей: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('test_results', 1);
    }
}
