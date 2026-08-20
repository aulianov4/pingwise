<?php

namespace Tests\Feature\Commands;

use App\Events\ServerStatusChanged;
use App\Models\Server;
use App\Services\Servers\ServerHeartbeatService;
use Database\Factories\ServerHeartbeatFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CheckServerSilenceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_silent_servers(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create();
        app(ServerHeartbeatService::class)->ingest(
            $server,
            ServerHeartbeatFactory::healthyValue(),
            now()->subMinutes(40),
        );

        $this->artisan('pingwise:servers:check-silence')
            ->expectsOutputToContain('Отмечено молчащих: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('server_heartbeats', [
            'server_id' => $server->id,
            'source' => 'silence',
            'status' => 'failed',
        ]);
    }

    public function test_command_skips_servers_without_first_ping(): void
    {
        Event::fake([ServerStatusChanged::class]);

        Server::factory()->create();

        $this->artisan('pingwise:servers:check-silence')
            ->expectsOutputToContain('Отмечено молчащих: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('server_heartbeats', 0);
        Event::assertNotDispatched(ServerStatusChanged::class);
    }
}
