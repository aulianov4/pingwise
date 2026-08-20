<?php

namespace Database\Factories;

use App\Models\NotificationChannel;
use App\Models\Server;
use App\Models\ServerNotificationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerNotificationChannel>
 */
class ServerNotificationChannelFactory extends Factory
{
    protected $model = ServerNotificationChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'notification_channel_id' => NotificationChannel::factory()->connected(),
            'alerts' => false,
            'daily_summary' => false,
            'weekly_summary' => false,
            'monthly_summary' => false,
        ];
    }

    public function alerts(): static
    {
        return $this->state(fn (array $attributes): array => [
            'alerts' => true,
        ]);
    }

    public function dailySummary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'daily_summary' => true,
        ]);
    }
}
