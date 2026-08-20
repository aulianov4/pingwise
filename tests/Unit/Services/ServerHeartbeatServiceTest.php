<?php

namespace Tests\Unit\Services;

use App\Events\ServerStatusChanged;
use App\Models\Server;
use App\Models\ServerHeartbeat;
use App\Services\Servers\ServerHeartbeatService;
use Database\Factories\ServerHeartbeatFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerHeartbeatServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_replace_recursive(ServerHeartbeatFactory::healthyValue(), $overrides);
    }

    public function test_ingest_stores_heartbeat_and_updates_last_seen(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create();
        $heartbeat = app(ServerHeartbeatService::class)->ingest($server, $this->payload());

        $server->refresh();

        $this->assertSame('success', $heartbeat->status);
        $this->assertSame(ServerHeartbeat::SOURCE_HEARTBEAT, $heartbeat->source);
        $this->assertSame('web-01', $server->hostname);
        $this->assertNotNull($server->last_seen_at);
        $this->assertSame('success', $server->last_status);
        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    public function test_first_failed_ingest_does_not_dispatch(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create();
        $payload = $this->payload();
        $payload['memory']['available_bytes'] = 32 * 1024 * 1024;

        app(ServerHeartbeatService::class)->ingest($server, $payload);

        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    public function test_status_change_dispatches_event(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create();
        $service = app(ServerHeartbeatService::class);
        $service->ingest($server, $this->payload());

        $payload = $this->payload();
        $payload['memory']['available_bytes'] = 32 * 1024 * 1024;
        $service->ingest($server->fresh(), $payload);

        Event::assertDispatched(ServerStatusChanged::class);
    }

    public function test_mark_silent_skips_server_without_ping(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['last_seen_at' => null]);
        $result = app(ServerHeartbeatService::class)->markSilentIfNeeded($server);

        $this->assertNull($result);
        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    public function test_mark_silent_creates_failed_heartbeat_after_timeout(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create();
        $service = app(ServerHeartbeatService::class);
        $service->ingest($server, $this->payload(), now()->subMinutes(40));

        $result = $service->markSilentIfNeeded($server->fresh(), now());

        $this->assertNotNull($result);
        $this->assertSame(ServerHeartbeat::SOURCE_SILENCE, $result->source);
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('Нет пинга', $result->message);
        Event::assertDispatched(ServerStatusChanged::class);
    }

    public function test_mark_silent_does_not_repeat(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create();
        $service = app(ServerHeartbeatService::class);
        $service->ingest($server, $this->payload(), now()->subMinutes(40));
        $service->markSilentIfNeeded($server->fresh(), now());

        Event::fake([ServerStatusChanged::class]);

        $again = $service->markSilentIfNeeded($server->fresh(), now());

        $this->assertNull($again);
        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    public function test_recovery_after_silence_dispatches(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create();
        $service = app(ServerHeartbeatService::class);
        $service->ingest($server, $this->payload(), now()->subMinutes(40));
        $service->markSilentIfNeeded($server->fresh());
        $service->ingest($server->fresh(), $this->payload());

        Event::assertDispatchedTimes(ServerStatusChanged::class, 2);
        $this->assertSame('success', $server->fresh()->last_status);
    }
}
