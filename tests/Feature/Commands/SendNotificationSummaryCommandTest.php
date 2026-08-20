<?php

namespace Tests\Feature\Commands;

use App\Models\NotificationChannel;
use App\Models\Site;
use App\Models\SiteTest;
use App\Models\SiteTestNotificationChannel;
use App\Models\TestResult;
use App\Services\Telegram\TelegramBotInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SendNotificationSummaryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_sends_one_message_per_channel(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $channel = NotificationChannel::factory()->connected()->create();
        $siteA = Site::factory()->createQuietly(['project_id' => $channel->project_id]);
        $siteB = Site::factory()->createQuietly(['project_id' => $channel->project_id]);

        $testA = SiteTest::factory()->availability()->create(['site_id' => $siteA->id]);
        $testB = SiteTest::factory()->availability()->create(['site_id' => $siteB->id]);

        SiteTestNotificationChannel::factory()->dailySummary()->create([
            'site_test_id' => $testA->id,
            'notification_channel_id' => $channel->id,
        ]);
        SiteTestNotificationChannel::factory()->dailySummary()->create([
            'site_test_id' => $testB->id,
            'notification_channel_id' => $channel->id,
        ]);

        TestResult::factory()->availability()->create([
            'site_id' => $siteA->id,
            'checked_at' => now()->subHours(2),
        ]);
        TestResult::factory()->availability()->create([
            'site_id' => $siteB->id,
            'checked_at' => now()->subHours(1),
        ]);

        $this->artisan('pingwise:notifications:summary', ['--period' => 'daily'])
            ->expectsOutput('Саммари (daily) отправлено в 1 канал(ов).')
            ->assertSuccessful();
    }

    public function test_summary_skips_channel_without_flag(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sendMessage');
        });

        $channel = NotificationChannel::factory()->connected()->create();
        $site = Site::factory()->createQuietly(['project_id' => $channel->project_id]);
        $siteTest = SiteTest::factory()->availability()->create(['site_id' => $site->id]);

        SiteTestNotificationChannel::factory()->create([
            'site_test_id' => $siteTest->id,
            'notification_channel_id' => $channel->id,
            'daily_summary' => false,
            'weekly_summary' => true,
        ]);

        TestResult::factory()->availability()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subHours(2),
        ]);

        $this->artisan('pingwise:notifications:summary', ['--period' => 'daily'])
            ->expectsOutput('Саммари (daily) отправлено в 0 канал(ов).')
            ->assertSuccessful();
    }

    public function test_invalid_period_fails(): void
    {
        $this->artisan('pingwise:notifications:summary', ['--period' => 'hourly'])
            ->assertFailed();
    }
}
