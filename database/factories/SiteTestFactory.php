<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteTest>
 */
class SiteTestFactory extends Factory
{
    protected $model = SiteTest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'test_type' => fake()->randomElement(['availability', 'ssl', 'domain', 'sitemap']),
            'is_enabled' => true,
            'settings' => [
                'interval_minutes' => 5,
            ],
        ];
    }

    /**
     * Тест доступности.
     */
    public function availability(): static
    {
        return $this->state(fn (array $attributes) => [
            'test_type' => 'availability',
            'settings' => ['interval_minutes' => 5],
        ]);
    }

    /**
     * Тест SSL.
     */
    public function ssl(): static
    {
        return $this->state(fn (array $attributes) => [
            'test_type' => 'ssl',
            'settings' => ['interval_minutes' => 1440],
        ]);
    }

    /**
     * Тест домена.
     */
    public function domain(): static
    {
        return $this->state(fn (array $attributes) => [
            'test_type' => 'domain',
            'settings' => ['interval_minutes' => 1440],
        ]);
    }

    /**
     * Тест аудита карты сайта.
     */
    public function sitemap(): static
    {
        return $this->state(fn (array $attributes) => [
            'test_type' => 'sitemap',
            'settings' => [
                'interval_minutes' => 1440,
                'max_crawl_pages' => 5000,
                'crawl_timeout_seconds' => 300,
                'sitemap_url' => '/sitemap.xml',
                'check_concurrency' => 10,
            ],
        ]);
    }

    /**
     * Выключенный тест.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
