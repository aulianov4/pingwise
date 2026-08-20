<?php

namespace Tests\Unit\Services;

use App\Enums\SummaryPeriod;
use App\Models\Server;
use App\Models\ServerHeartbeat;
use App\Models\ServerNotificationChannel;
use App\Services\Servers\ServerSummaryBuilder;
use Database\Factories\ServerHeartbeatFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerSummaryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_awaiting_agent_has_no_period_noise(): void
    {
        $server = Server::factory()->create(['last_seen_at' => null]);
        $assignment = ServerNotificationChannel::factory()->dailySummary()->create([
            'server_id' => $server->id,
        ]);
        $assignment->setRelation('server', $server);

        $items = app(ServerSummaryBuilder::class)->items(collect([$assignment]), SummaryPeriod::Daily);

        $this->assertCount(1, $items);
        $this->assertTrue($items[0]->awaitingAgent);
        $this->assertSame(0, $items[0]->silenceIncidents);
        $this->assertSame(0, $items[0]->rebootCount);
    }

    public function test_counts_silence_warnings_and_reboots(): void
    {
        $server = Server::factory()->create([
            'hostname' => 'web-01',
            'last_seen_at' => now()->subMinutes(5),
            'last_status' => 'success',
        ]);
        $assignment = ServerNotificationChannel::factory()->dailySummary()->create([
            'server_id' => $server->id,
        ]);
        $assignment->load('server.latestHeartbeat');

        $healthy = ServerHeartbeatFactory::healthyValue();
        ServerHeartbeat::factory()->create([
            'server_id' => $server->id,
            'status' => 'success',
            'value' => array_merge($healthy, ['uptime_seconds' => 100000]),
            'reported_at' => now()->subHours(6),
        ]);
        ServerHeartbeat::factory()->create([
            'server_id' => $server->id,
            'status' => 'warning',
            'message' => 'Диск /: занято 87%, свободно 1.4 ГБ',
            'value' => array_merge($healthy, ['uptime_seconds' => 100000]),
            'reported_at' => now()->subHours(4),
        ]);
        ServerHeartbeat::factory()->create([
            'server_id' => $server->id,
            'status' => 'warning',
            'message' => 'Сервер перезагрузился (аптайм 2 мин)',
            'value' => array_merge($healthy, ['uptime_seconds' => 120]),
            'reported_at' => now()->subHours(2),
        ]);
        ServerHeartbeat::factory()->silence()->create([
            'server_id' => $server->id,
            'reported_at' => now()->subHour(),
        ]);

        $items = app(ServerSummaryBuilder::class)->items(collect([$assignment->fresh(['server.latestHeartbeat'])]), SummaryPeriod::Daily);

        $this->assertSame(1, $items[0]->silenceIncidents);
        $this->assertSame(1, $items[0]->warningCount);
        $this->assertSame(['диск'], $items[0]->warningReasons);
        $this->assertSame(1, $items[0]->rebootCount);
        $this->assertTrue($items[0]->currentlySilent);
        $this->assertNotNull($items[0]->ramAvailablePercent);
    }
}
