<?php

namespace Database\Factories;

use App\Models\NotificationChannel;
use App\Models\Site;
use App\Models\SiteTest;
use App\Models\SiteTestNotificationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteTestNotificationChannel>
 */
class SiteTestNotificationChannelFactory extends Factory
{
    protected $model = SiteTestNotificationChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_test_id' => fn (): int => SiteTest::factory()->availability()->create([
                'site_id' => Site::factory()->createQuietly()->id,
            ])->id,
            'notification_channel_id' => NotificationChannel::factory()->connected(),
            'alerts' => false,
            'daily_summary' => false,
            'weekly_summary' => false,
            'monthly_summary' => false,
        ];
    }

    /**
     * Алерты включены.
     */
    public function alerts(): static
    {
        return $this->state(fn (array $attributes): array => [
            'alerts' => true,
        ]);
    }

    /**
     * Ежесуточное саммари включено.
     */
    public function dailySummary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'daily_summary' => true,
        ]);
    }
}
