<?php

namespace Tests\Feature\Commands;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitTestsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_init_tests_creates_tests_for_site(): void
    {
        $site = Site::factory()->createQuietly();
        $this->artisan('pingwise:init-tests', ['--site' => $site->id])
            ->assertSuccessful();
        $this->assertDatabaseCount('site_tests', 3);
        $this->assertDatabaseHas('site_tests', [
            'site_id' => $site->id,
            'test_type' => 'availability',
        ]);
        $this->assertDatabaseHas('site_tests', [
            'site_id' => $site->id,
            'test_type' => 'ssl',
        ]);
        $this->assertDatabaseHas('site_tests', [
            'site_id' => $site->id,
            'test_type' => 'domain',
        ]);
    }

    public function test_init_tests_for_all_sites(): void
    {
        Site::factory()->count(3)->createQuietly();
        $this->artisan('pingwise:init-tests')
            ->assertSuccessful();
        $this->assertDatabaseCount('site_tests', 9);
    }

    public function test_init_tests_with_invalid_site_returns_failure(): void
    {
        $this->artisan('pingwise:init-tests', ['--site' => 999])
            ->expectsOutput('Сайт с ID 999 не найден')
            ->assertFailed();
    }

    public function test_init_tests_does_not_duplicate_existing(): void
    {
        $site = Site::factory()->createQuietly();
        $this->artisan('pingwise:init-tests', ['--site' => $site->id])
            ->assertSuccessful();
        $this->artisan('pingwise:init-tests', ['--site' => $site->id])
            ->assertSuccessful();
        $this->assertDatabaseCount('site_tests', 3);
    }
}
