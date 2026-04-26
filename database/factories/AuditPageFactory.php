<?php

namespace Database\Factories;

use App\Models\AuditPage;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditPage>
 */
class AuditPageFactory extends Factory
{
    protected $model = AuditPage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = fake()->url();
        $now = now();

        return [
            'site_id' => Site::factory(),
            'url' => $url,
            'status_code' => 200,
            'in_sitemap' => true,
            'in_crawl' => true,
            'crawl_depth' => 1,
            'redirect_target' => null,
            'canonical' => null,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'last_in_sitemap_at' => $now,
            'removed_from_sitemap_at' => null,
        ];
    }

    /**
     * Мёртвая страница (status_code=0, в sitemap).
     */
    public function dead(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => 0,
            'in_sitemap' => true,
        ]);
    }

    /**
     * Страница с редиректом в sitemap.
     */
    public function redirecting(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => 301,
            'in_sitemap' => true,
            'redirect_target' => fake()->url(),
        ]);
    }

    /**
     * Страница отсутствует в sitemap, но найдена краулером.
     */
    public function missingFromSitemap(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_sitemap' => false,
            'in_crawl' => true,
            'last_in_sitemap_at' => null,
        ]);
    }

    /**
     * Страница с canonical-проблемой.
     */
    public function withCanonicalIssue(): static
    {
        return $this->state(fn (array $attributes) => [
            'canonical' => fake()->url(),
        ]);
    }
}
