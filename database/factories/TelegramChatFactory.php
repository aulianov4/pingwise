<?php

namespace Database\Factories;

use App\Models\TelegramChat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramChat>
 */
class TelegramChatFactory extends Factory
{
    protected $model = TelegramChat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_id' => fake()->unique()->numberBetween(-999999999, -100000),
            'title' => fake()->company().' — мониторинг',
            'type' => fake()->randomElement(['group', 'supergroup']),
        ];
    }

    /**
     * Супергруппа.
     */
    public function supergroup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'supergroup',
        ]);
    }

    /**
     * Канал.
     */
    public function channel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'channel',
        ]);
    }
}
