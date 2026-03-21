<?php

namespace Tests\Feature\Commands;

use App\Models\Site;
use App\Models\SiteTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunTestsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_command_exits_successfully(): void
    {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $this->artisan('pingwise:check')
            ->assertSuccessful();
    }

    public function test_check_command_with_invalid_site_returns_failure(): void
    {
        $this->artisan('pingwise:check', ['--site' => 999, '--test' => 'availability'])
            ->expectsOutput('Сайт с ID 999 не найден')
            ->assertFailed();
    }

    public function test_check_command_with_invalid_test_returns_failure(): void
    {
        $site = Site::factory()->createQuietly();

        $this->artisan('pingwise:check', ['--site' => $site->id, '--test' => 'nonexistent'])
            ->expectsOutput('Тест nonexistent не найден')
            ->assertFailed();
    }

    public function test_check_command_with_site_and_test_runs_specific_test(): void
    {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);

        $this->artisan('pingwise:check', ['--site' => $site->id, '--test' => 'availability'])
            ->assertSuccessful();

        $this->assertDatabaseHas('test_results', [
            'site_id' => $site->id,
            'test_type' => 'availability',
            'status' => 'success',
        ]);
    }

    public function test_check_command_with_site_only_runs_all_enabled_tests(): void
    {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        SiteTest::factory()->availability()->create(['site_id' => $site->id]);

        $this->artisan('pingwise:check', ['--site' => $site->id])
            ->assertSuccessful();
    }

    public function test_check_command_test_without_site_returns_failure(): void
    {
        $this->artisan('pingwise:check', ['--test' => 'availability'])
            ->expectsOutput('Опция --test требует указания --site')
            ->assertFailed();
    }
}
