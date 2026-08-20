<?php

namespace Tests\Feature\Api;

use App\Models\Server;
use Database\Factories\ServerHeartbeatFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerHeartbeatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_requires_token(): void
    {
        $this->postJson('/api/v1/servers/heartbeat', ServerHeartbeatFactory::healthyValue())
            ->assertUnauthorized();
    }

    public function test_heartbeat_rejects_unknown_token(): void
    {
        $this->withToken('pw_srv_'.str_repeat('ab', 24))
            ->postJson('/api/v1/servers/heartbeat', ServerHeartbeatFactory::healthyValue())
            ->assertUnauthorized();
    }

    public function test_heartbeat_rejects_inactive_server(): void
    {
        $plain = 'pw_srv_'.str_repeat('cd', 24);
        Server::factory()->inactive()->withToken($plain)->create();

        $this->withToken($plain)
            ->postJson('/api/v1/servers/heartbeat', ServerHeartbeatFactory::healthyValue())
            ->assertUnauthorized();
    }

    public function test_heartbeat_accepts_valid_payload(): void
    {
        $plain = 'pw_srv_'.str_repeat('ef', 24);
        $server = Server::factory()->withToken($plain)->create();

        $response = $this->withToken($plain)
            ->postJson('/api/v1/servers/heartbeat', ServerHeartbeatFactory::healthyValue());

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('next_interval_seconds', 600);

        $this->assertDatabaseHas('server_heartbeats', [
            'server_id' => $server->id,
            'source' => 'heartbeat',
            'status' => 'success',
        ]);

        $this->assertSame('web-01', $server->fresh()->hostname);
    }

    public function test_heartbeat_validates_payload(): void
    {
        $plain = 'pw_srv_'.str_repeat('aa', 24);
        Server::factory()->withToken($plain)->create();

        $this->withToken($plain)
            ->postJson('/api/v1/servers/heartbeat', ['hostname' => 'x'])
            ->assertStatus(422);
    }
}
