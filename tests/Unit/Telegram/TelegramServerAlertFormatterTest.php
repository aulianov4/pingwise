<?php

namespace Tests\Unit\Telegram;

use App\DTO\ServerSummaryItem;
use App\Enums\SummaryPeriod;
use App\Models\NotificationChannel;
use App\Models\Server;
use App\Models\ServerHeartbeat;
use App\Services\Telegram\TelegramMessageFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramServerAlertFormatterTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_server_alert_contains_name_and_status(): void
    {
        $server = Server::factory()->create(['name' => 'prod-db', 'hostname' => 'db-01']);
        $previous = ServerHeartbeat::factory()->create([
            'server_id' => $server->id,
            'status' => 'success',
            'message' => 'OK',
        ]);
        $current = ServerHeartbeat::factory()->silence()->create([
            'server_id' => $server->id,
            'reported_at' => now(),
        ]);

        $message = app(TelegramMessageFormatter::class)->formatServerAlert($server, $current, $previous);

        $this->assertStringContainsString('prod-db', $message);
        $this->assertStringContainsString('db-01', $message);
        $this->assertStringContainsString('Нет пинга', $message);
        $this->assertStringContainsString('Успешно', $message);
        $this->assertStringContainsString('Ошибка', $message);
    }

    public function test_format_server_channel_summary_contains_snapshot_and_period(): void
    {
        $channel = NotificationChannel::factory()->connected()->create(['name' => 'DevOps']);
        $server = Server::factory()->create([
            'name' => 'web-01',
            'hostname' => 'web-01.internal',
            'project_id' => $channel->project_id,
            'last_status' => 'success',
            'last_seen_at' => now(),
        ]);

        $item = new ServerSummaryItem(
            server: $server,
            awaitingAgent: false,
            currentlySilent: false,
            status: 'success',
            lastMessage: 'OK',
            lastSeenAt: now(),
            silentSince: null,
            silentMinutes: null,
            ramAvailablePercent: 38.0,
            diskUsedPercent: 61.0,
            uptimeSeconds: 47 * 86400,
            silenceIncidents: 0,
            warningCount: 1,
            failedCount: 0,
            warningReasons: ['диск'],
            failedReasons: [],
            rebootCount: 0,
        );

        $message = app(TelegramMessageFormatter::class)->formatServerChannelSummary(
            $channel,
            SummaryPeriod::Daily,
            [$item],
        );

        $this->assertStringContainsString('Серверы за сутки', $message);
        $this->assertStringContainsString('web-01', $message);
        $this->assertStringContainsString('RAM доступно 38%', $message);
        $this->assertStringContainsString('диск 61%', $message);
        $this->assertStringContainsString('аптайм 47 дн.', $message);
        $this->assertStringContainsString('тишина 0', $message);
        $this->assertStringContainsString('warning 1 (диск)', $message);
        $this->assertStringContainsString('reboot 0', $message);
    }

    public function test_format_server_channel_summary_awaiting_agent(): void
    {
        $channel = NotificationChannel::factory()->connected()->create();
        $server = Server::factory()->create([
            'name' => 'new-box',
            'project_id' => $channel->project_id,
            'last_seen_at' => null,
        ]);

        $item = new ServerSummaryItem(
            server: $server,
            awaitingAgent: true,
            currentlySilent: false,
            status: null,
            lastMessage: null,
            lastSeenAt: null,
            silentSince: null,
            silentMinutes: null,
            ramAvailablePercent: null,
            diskUsedPercent: null,
            uptimeSeconds: null,
            silenceIncidents: 0,
            warningCount: 0,
            failedCount: 0,
            warningReasons: [],
            failedReasons: [],
            rebootCount: 0,
        );

        $message = app(TelegramMessageFormatter::class)->formatServerChannelSummary(
            $channel,
            SummaryPeriod::Daily,
            [$item],
        );

        $this->assertStringContainsString('new-box', $message);
        $this->assertStringContainsString('Ожидает агент', $message);
    }
}
