<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->domainWord().' '.fake()->randomElement(['сайт', 'портал', 'сервис']),
            'url' => fake()->randomElement(['https://']).fake()->domainName(),
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Неактивный сайт.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * С включёнными Telegram-алертами.
     */
    public function withTelegramAlerts(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_settings' => [
                'alerts_enabled' => true,
                'summary_enabled' => false,
            ],
        ]);
    }

    /**
     * С включённым ежесуточным саммари.
     */
    public function withTelegramSummary(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_settings' => [
                'alerts_enabled' => false,
                'summary_enabled' => true,
            ],
        ]);
    }
}
