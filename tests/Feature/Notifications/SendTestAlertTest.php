<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationChannel;
use App\Models\Site;
use App\Models\SiteTest;
use App\Models\SiteTestNotificationChannel;
use App\Models\TestResult;
use App\Services\Telegram\TelegramBotInterface;
use App\Services\TestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class SendTestAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_does_not_send_alert(): void
    {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sendMessage');
        });

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $siteTest = SiteTest::factory()->availability()->create(['site_id' => $site->id]);
        $channel = NotificationChannel::factory()->connected()->create([
            'project_id' => $site->project_id,
        ]);
        SiteTestNotificationChannel::factory()->alerts()->create([
            'site_test_id' => $siteTest->id,
            'notification_channel_id' => $channel->id,
        ]);

        $site->load('siteTests');
        app(TestService::class)->runTest($site, 'availability');

        $this->assertDatabaseCount('test_results', 1);
    }

    public function test_status_change_sends_alert_when_pivot_enabled(): void
    {
        Http::fake([
            '*' => Http::response('err', 500),
        ]);

        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $siteTest = SiteTest::factory()->availability()->create(['site_id' => $site->id]);
        $channel = NotificationChannel::factory()->connected()->create([
            'project_id' => $site->project_id,
        ]);
        SiteTestNotificationChannel::factory()->alerts()->create([
            'site_test_id' => $siteTest->id,
            'notification_channel_id' => $channel->id,
        ]);

        foreach ([20, 10] as $minutesAgo) {
            TestResult::factory()->availability()->failed()->create([
                'site_id' => $site->id,
                'checked_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        $site->load('siteTests');
        app(TestService::class)->runTest($site, 'availability');
    }

    public function test_single_availability_failure_does_not_send_alert(): void
    {
        Http::fake([
            '*' => Http::response('err', 500),
        ]);

        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sendMessage');
        });

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $siteTest = SiteTest::factory()->availability()->create(['site_id' => $site->id]);
        $channel = NotificationChannel::factory()->connected()->create([
            'project_id' => $site->project_id,
        ]);
        SiteTestNotificationChannel::factory()->alerts()->create([
            'site_test_id' => $siteTest->id,
            'notification_channel_id' => $channel->id,
        ]);
        TestResult::factory()->availability()->failed()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subMinutes(10),
        ]);

        $site->load('siteTests');
        app(TestService::class)->runTest($site, 'availability');
    }

    public function test_status_change_does_not_send_without_alert_flag(): void
    {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sendMessage');
        });

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $siteTest = SiteTest::factory()->availability()->create(['site_id' => $site->id]);
        $channel = NotificationChannel::factory()->connected()->create([
            'project_id' => $site->project_id,
        ]);
        SiteTestNotificationChannel::factory()->create([
            'site_test_id' => $siteTest->id,
            'notification_channel_id' => $channel->id,
            'alerts' => false,
        ]);
        TestResult::factory()->availability()->failed()->create([
            'site_id' => $site->id,
            'checked_at' => now()->subMinutes(10),
        ]);

        $site->load('siteTests');
        app(TestService::class)->runTest($site, 'availability');
    }
}
