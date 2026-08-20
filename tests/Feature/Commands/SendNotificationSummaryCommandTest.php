<?php

namespace Tests\Feature\Commands;

use App\Models\NotificationChannel;
use App\Models\Server;
use App\Models\ServerHeartbeat;
use App\Models\ServerNotificationChannel;
use App\Models\Site;
use App\Models\SiteTest;
use App\Models\SiteTestNotificationChannel;
use App\Models\TestResult;
use App\Services\Telegram\TelegramBotInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_server_summary_sends_separate_message(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function (int $chatId, string $text): bool {
                    return str_contains($text, 'Серверы за сутки')
                        && str_contains($text, 'web-01');
                })
                ->andReturn(true);
        });

        $channel = NotificationChannel::factory()->connected()->create();
        $server = Server::factory()->create([
            'name' => 'web-01',
            'hostname' => 'web-01.internal',
            'project_id' => $channel->project_id,
            'last_status' => 'success',
            'last_seen_at' => now()->subMinutes(8),
        ]);
        ServerNotificationChannel::factory()->dailySummary()->create([
            'server_id' => $server->id,
            'notification_channel_id' => $channel->id,
        ]);
        ServerHeartbeat::factory()->create([
            'server_id' => $server->id,
            'reported_at' => now()->subMinutes(8),
        ]);

        $this->artisan('pingwise:notifications:summary', ['--period' => 'daily'])
            ->expectsOutput('Саммари (daily) отправлено в 1 канал(ов).')
            ->assertSuccessful();
    }

    public function test_site_and_server_summaries_are_two_messages(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->twice()->andReturn(true);
        });

        $channel = NotificationChannel::factory()->connected()->create();
        $site = Site::factory()->createQuietly(['project_id' => $channel->project_id]);
        $siteTest = SiteTest::factory()->availability()->create(['site_id' => $site->id]);
        SiteTestNotificationChannel::factory()->dailySummary()->create([
            'site_test_id' => $siteTest->id,
            'notification_channel_id' => $channel->id,
        ]);
        TestResult::factory()->availability()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subHours(2),
        ]);

        $server = Server::factory()->create([
            'project_id' => $channel->project_id,
            'last_status' => 'success',
            'last_seen_at' => now(),
        ]);
        ServerNotificationChannel::factory()->dailySummary()->create([
            'server_id' => $server->id,
            'notification_channel_id' => $channel->id,
        ]);
        ServerHeartbeat::factory()->create([
            'server_id' => $server->id,
            'reported_at' => now(),
        ]);

        $this->artisan('pingwise:notifications:summary', ['--period' => 'daily'])
            ->expectsOutput('Саммари (daily) отправлено в 2 канал(ов).')
            ->assertSuccessful();
    }

    public function test_server_summary_skips_without_flag(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sendMessage');
        });

        $channel = NotificationChannel::factory()->connected()->create();
        $server = Server::factory()->create(['project_id' => $channel->project_id]);
        ServerNotificationChannel::factory()->alerts()->create([
            'server_id' => $server->id,
            'notification_channel_id' => $channel->id,
            'daily_summary' => false,
        ]);

        $this->artisan('pingwise:notifications:summary', ['--period' => 'daily'])
            ->expectsOutput('Саммари (daily) отправлено в 0 канал(ов).')
            ->assertSuccessful();
    }

    public function test_due_sends_only_channels_with_matching_time(): void
    {
        $this->travelTo(Carbon::parse('2026-08-21 06:00:00', 'UTC'));

        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $dueChannel = NotificationChannel::factory()->connected()->create([
            'summary_time' => '09:00',
        ]);
        $laterChannel = NotificationChannel::factory()->connected()->create([
            'project_id' => $dueChannel->project_id,
            'summary_time' => '18:00',
        ]);

        foreach ([$dueChannel, $laterChannel] as $channel) {
            $site = Site::factory()->createQuietly(['project_id' => $channel->project_id]);
            $siteTest = SiteTest::factory()->availability()->create(['site_id' => $site->id]);
            SiteTestNotificationChannel::factory()->dailySummary()->create([
                'site_test_id' => $siteTest->id,
                'notification_channel_id' => $channel->id,
            ]);
            TestResult::factory()->availability()->create([
                'site_id' => $site->id,
                'checked_at' => now()->subHours(2),
            ]);
        }

        $this->artisan('pingwise:notifications:summary', ['--due' => true])
            ->expectsOutput('Саммари (daily) отправлено в 1 канал(ов).')
            ->assertSuccessful();
    }

    public function test_due_skips_when_time_does_not_match(): void
    {
        $this->travelTo(Carbon::parse('2026-08-21 09:00:00', 'UTC'));

        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sendMessage');
        });

        $channel = NotificationChannel::factory()->connected()->create([
            'summary_time' => '09:00',
        ]);
        $site = Site::factory()->createQuietly(['project_id' => $channel->project_id]);
        $siteTest = SiteTest::factory()->availability()->create(['site_id' => $site->id]);
        SiteTestNotificationChannel::factory()->dailySummary()->create([
            'site_test_id' => $siteTest->id,
            'notification_channel_id' => $channel->id,
        ]);
        TestResult::factory()->availability()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subHours(2),
        ]);

        $this->artisan('pingwise:notifications:summary', ['--due' => true])
            ->expectsOutput('Саммари (daily) отправлено в 0 канал(ов).')
            ->assertSuccessful();
    }
}
