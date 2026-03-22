<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\TestResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestResult>
 */
class TestResultFactory extends Factory
{
    protected $model = TestResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'test_type' => fake()->randomElement(['availability', 'ssl', 'domain', 'sitemap']),
            'status' => 'success',
            'value' => null,
            'message' => 'Тест выполнен успешно',
            'checked_at' => now(),
        ];
    }

    /**
     * Результат с ошибкой.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'message' => 'Тест завершился с ошибкой',
        ]);
    }

    /**
     * Результат с предупреждением.
     */
    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'warning',
            'message' => 'Тест завершился с предупреждением',
        ]);
    }

    /**
     * Результат для теста доступности.
     */
    public function availability(): static
    {
        return $this->state(fn (array $attributes) => [
            'test_type' => 'availability',
            'value' => [
                'status_code' => 200,
                'response_time_ms' => fake()->numberBetween(50, 500),
                'is_up' => true,
            ],
        ]);
    }

    /**
     * Результат для SSL-теста.
     */
    public function ssl(): static
    {
        return $this->state(fn (array $attributes) => [
            'test_type' => 'ssl',
            'value' => [
                'is_valid' => true,
                'days_until_expiry' => fake()->numberBetween(30, 365),
            ],
        ]);
    }

    /**
     * Результат для теста домена.
     */
    public function domain(): static
    {
        return $this->state(fn (array $attributes) => [
            'test_type' => 'domain',
            'value' => [
                'domain' => fake()->domainName(),
                'days_since_registration' => fake()->numberBetween(100, 5000),
            ],
        ]);
    }

    /**
     * Результат для аудита карты сайта.
     */
    public function sitemap(): static
    {
        return $this->state(fn (array $attributes) => [
            'test_type' => 'sitemap',
            'value' => [
                'sitemap_urls_count' => fake()->numberBetween(10, 200),
                'crawled_urls_count' => fake()->numberBetween(10, 100),
                'dead_pages' => [],
                'missing_from_sitemap' => [],
                'redirecting_in_sitemap' => [],
                'non_200_pages' => [],
                'canonical_issues' => [],
                'has_sitemap' => true,
                'crawl_limited' => false,
                'sitemap_parse_errors' => [],
            ],
        ]);
    }
}
