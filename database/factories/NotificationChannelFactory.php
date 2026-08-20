<?php

namespace Database\Factories;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    protected $model = NotificationChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(2, true),
            'type' => NotificationChannelType::Telegram,
            'is_enabled' => true,
            'telegram_chat_id' => null,
            'connect_token' => 'PW-'.Str::upper(Str::random(4)),
            'connect_token_expires_at' => now()->addMinutes(NotificationChannel::CONNECT_TOKEN_TTL_MINUTES),
            'config' => null,
        ];
    }

    /**
     * Подключённый Telegram-канал.
     */
    public function connected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'telegram_chat_id' => fake()->unique()->numberBetween(-999999999, -100000),
            'connect_token' => null,
            'connect_token_expires_at' => null,
            'config' => [
                'chat_title' => fake()->company(),
                'chat_type' => 'supergroup',
                'connected_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Просроченный код подключения.
     */
    public function expiredToken(): static
    {
        return $this->state(fn (array $attributes): array => [
            'connect_token_expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Выключенный канал.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_enabled' => false,
        ]);
    }
}
