<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\ServerHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerHeartbeat>
 */
class ServerHeartbeatFactory extends Factory
{
    protected $model = ServerHeartbeat::class;

    /**
     * @return array<string, mixed>
     */
    public static function healthyValue(): array
    {
        return [
            'hostname' => 'web-01',
            'uptime_seconds' => 86400,
            'cpu' => [
                'load1' => 0.2,
                'load5' => 0.2,
                'load15' => 0.2,
                'cores' => 4,
            ],
            'memory' => [
                'total_bytes' => 8 * 1024 * 1024 * 1024,
                'available_bytes' => 4 * 1024 * 1024 * 1024,
                'swap_total_bytes' => 0,
                'swap_free_bytes' => 0,
            ],
            'disks' => [[
                'mount' => '/',
                'total_bytes' => 100 * 1024 * 1024 * 1024,
                'used_bytes' => 40 * 1024 * 1024 * 1024,
                'inodes_total' => 1_000_000,
                'inodes_used' => 100_000,
                'fstype' => 'ext4',
            ]],
            'agent_version' => '1.0.0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'source' => ServerHeartbeat::SOURCE_HEARTBEAT,
            'status' => 'success',
            'value' => self::healthyValue(),
            'message' => 'OK',
            'reported_at' => now(),
        ];
    }

    public function silence(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => ServerHeartbeat::SOURCE_SILENCE,
            'status' => 'failed',
            'value' => [
                'silence' => true,
                'minutes_without_ping' => 35,
            ],
            'message' => 'Нет пинга 35 мин',
        ]);
    }
}
