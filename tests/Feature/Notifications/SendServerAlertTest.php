<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationChannel;
use App\Models\Server;
use App\Models\ServerNotificationChannel;
use App\Services\Servers\ServerHeartbeatService;
use App\Services\Telegram\TelegramBotInterface;
use Database\Factories\ServerHeartbeatFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SendServerAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_heartbeat_does_not_send_alert(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sendMessage');
        });

        $server = Server::factory()->create();
        $channel = NotificationChannel::factory()->connected()->create([
            'project_id' => $server->project_id,
        ]);
        ServerNotificationChannel::factory()->alerts()->create([
            'server_id' => $server->id,
            'notification_channel_id' => $channel->id,
        ]);

        app(ServerHeartbeatService::class)->ingest($server, ServerHeartbeatFactory::healthyValue());
    }

    public function test_status_change_sends_alert_when_pivot_enabled(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $server = Server::factory()->create();
        $channel = NotificationChannel::factory()->connected()->create([
            'project_id' => $server->project_id,
        ]);
        ServerNotificationChannel::factory()->alerts()->create([
            'server_id' => $server->id,
            'notification_channel_id' => $channel->id,
        ]);

        $service = app(ServerHeartbeatService::class);
        $service->ingest($server, ServerHeartbeatFactory::healthyValue());

        $payload = ServerHeartbeatFactory::healthyValue();
        $payload['memory']['available_bytes'] = 32 * 1024 * 1024;
        $service->ingest($server->fresh(), $payload);
    }

    public function test_status_change_does_not_send_without_alert_flag(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sendMessage');
        });

        $server = Server::factory()->create();
        $channel = NotificationChannel::factory()->connected()->create([
            'project_id' => $server->project_id,
        ]);
        ServerNotificationChannel::factory()->create([
            'server_id' => $server->id,
            'notification_channel_id' => $channel->id,
            'alerts' => false,
        ]);

        $service = app(ServerHeartbeatService::class);
        $service->ingest($server, ServerHeartbeatFactory::healthyValue());

        $payload = ServerHeartbeatFactory::healthyValue();
        $payload['memory']['available_bytes'] = 32 * 1024 * 1024;
        $service->ingest($server->fresh(), $payload);
    }
}
