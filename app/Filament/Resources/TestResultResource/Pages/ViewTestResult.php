<?php

namespace App\Filament\Resources\TestResultResource\Pages;

use App\Filament\Resources\TestResultResource;
use App\Models\AuditPage;
use App\Models\TestResult;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewTestResult extends ViewRecord
{
    protected static string $resource = TestResultResource::class;

    public function content(Schema $schema): Schema
    {
        if ($this->record->test_type === 'sitemap') {
            return $schema->components([
                View::make('filament.resources.test-result.sitemap-audit')
                    ->viewData([
                        'record' => $this->record,
                        'data' => $this->record->value ?? [],
                        'site' => $this->record->site,
                        ...$this->computeSitemapMetrics(),
                    ]),
            ]);
        }

        return parent::content($schema);
    }

    /**
     * Подсчитать метрики для дашборда sitemap-аудита.
     * Агрегаты берём из TestResult.value, URL-списки — из текущего состояния AuditPage.
     *
     * @return array<string, mixed>
     */
    protected function computeSitemapMetrics(): array
    {
        $data = $this->record->value ?? [];
        $siteId = $this->record->site_id;

        $sitemapCount = $data['sitemap_urls_count'] ?? 0;
        $crawledCount = $data['crawled_urls_count'] ?? 0;
        $hasSitemap = $data['has_sitemap'] ?? false;
        $crawlLimited = $data['crawl_limited'] ?? false;
        $hasDeepPages = $data['has_deep_pages'] ?? false;
        $maxCrawlDepth = $data['max_crawl_depth'] ?? 0;
        $coverage = $data['coverage'] ?? 0;

        // ── Sitemap-вкладка ─────────────────────────────────────────────────────
        $sitemapPages = AuditPage::where('site_id', $siteId)
            ->where('in_sitemap', true)
            ->orderBy('status_code')
            ->orderBy('url')
            ->get(['url', 'status_code', 'redirect_target', 'canonical', 'crawl_depth', 'first_seen_at', 'last_in_sitemap_at'])
            ->toArray();

        $deadPages = array_filter($sitemapPages, fn ($p) => $p['status_code'] === 0);
        $non200Pages = array_filter($sitemapPages, fn ($p) => $p['status_code'] !== 0
            && $p['status_code'] !== 200
            && ($p['status_code'] < 300 || $p['status_code'] >= 400));
        $redirectingPages = array_filter($sitemapPages, fn ($p) => ($p['status_code'] >= 300
            && $p['status_code'] < 400) || $p['redirect_target'] !== null);

        // ── Crawl-вкладка ───────────────────────────────────────────────────────
        $crawlPages = AuditPage::where('site_id', $siteId)
            ->where('in_crawl', true)
            ->orderBy('crawl_depth')
            ->orderBy('url')
            ->get(['url', 'status_code', 'canonical', 'redirect_target', 'crawl_depth', 'in_sitemap', 'first_seen_at'])
            ->toArray();

        $orphanPages = array_filter($crawlPages, fn ($p) => ! $p['in_sitemap']);
        $crawlCanonicalIssues = array_filter($crawlPages, fn ($p) => $p['canonical'] !== null
            && $p['canonical'] !== $p['url']);

        // Распределение по глубинам
        $depthDistribution = [];
        foreach ($crawlPages as $page) {
            $d = $page['crawl_depth'] ?? 0;
            $depthDistribution[$d] = ($depthDistribution[$d] ?? 0) + 1;
        }
        ksort($depthDistribution);

        // ── Сравнение + тренды ──────────────────────────────────────────────────
        $coverageLevel = match (true) {
            $coverage >= 80 => 'success',
            $coverage >= 30 => 'warning',
            default => 'danger',
        };

        $brokenPages = array_merge(
            array_map(fn (array $p): array => ['url' => $p['url'], 'status' => 'не найдена'], array_values($deadPages)),
            array_map(fn (array $p): array => ['url' => $p['url'], 'status' => (string) $p['status_code']], array_values($non200Pages)),
        );

        $totalIssues = count($deadPages) + count($non200Pages) + count($redirectingPages)
            + count($orphanPages) + count($crawlCanonicalIssues);

        $healthScore = max(0, min(100,
            100
            - count($deadPages) * 2
            - count($non200Pages) * 3
            - count($redirectingPages) * 1
            - count($orphanPages) * 1
            - count($crawlCanonicalIssues) * 1
        ));

        $healthLevel = match (true) {
            $healthScore >= 80 => 'success',
            $healthScore >= 50 => 'warning',
            default => 'danger',
        };

        $insights = $this->buildInsights(
            $data, $coverage, $crawlLimited, $hasDeepPages, $maxCrawlDepth,
            $totalIssues, array_values($deadPages), array_values($orphanPages),
            array_values($redirectingPages), array_values($crawlCanonicalIssues),
        );

        // ── Тренды: последние 30 прогонов ───────────────────────────────────────
        $trendData = TestResult::where('site_id', $siteId)
            ->where('test_type', 'sitemap')
            ->orderByDesc('checked_at')
            ->limit(30)
            ->get(['checked_at', 'value'])
            ->map(fn (TestResult $r): array => [
                'date' => $r->checked_at->format('d.m'),
                'dead' => $r->value['dead_count'] ?? 0,
                'non_200' => $r->value['non_200_count'] ?? 0,
                'missing' => $r->value['missing_count'] ?? 0,
                'redirect' => $r->value['redirect_count'] ?? 0,
                'canonical' => $r->value['canonical_count'] ?? 0,
                'coverage' => $r->value['coverage'] ?? null,
                'sitemap_count' => $r->value['sitemap_urls_count'] ?? 0,
                'crawl_count' => $r->value['crawled_urls_count'] ?? 0,
            ])
            ->reverse()
            ->values()
            ->toArray();

        return [
            // Общее
            'hasSitemap' => $hasSitemap,
            'crawlLimited' => $crawlLimited,
            'hasDeepPages' => $hasDeepPages,
            'maxCrawlDepth' => $maxCrawlDepth,
            'sitemapCount' => $sitemapCount,
            'crawledCount' => $crawledCount,
            'coverage' => $coverage,
            'coverageLevel' => $coverageLevel,
            'healthScore' => $healthScore,
            'healthLevel' => $healthLevel,
            'totalIssues' => $totalIssues,
            'insights' => $insights,
            'brokenPages' => $brokenPages,
            // Вкладка Sitemap
            'sitemapPages' => $sitemapPages,
            'deadPages' => array_values($deadPages),
            'non200Pages' => array_values($non200Pages),
            'redirectingPages' => array_values($redirectingPages),
            // Вкладка Crawl
            'crawlPages' => $crawlPages,
            'orphanPages' => array_values($orphanPages),
            'crawlCanonicalIssues' => array_values($crawlCanonicalIssues),
            'depthDistribution' => $depthDistribution,
            // Тренды
            'trendData' => $trendData,
            // Diff
            'newSitemapCount' => $data['new_sitemap_count'] ?? 0,
            'removedSitemapCount' => $data['removed_sitemap_count'] ?? 0,
            'newCrawlCount' => $data['new_crawl_count'] ?? 0,
            'removedCrawlCount' => $data['removed_crawl_count'] ?? 0,
            'isFirstRun' => $data['is_first_run'] ?? false,
        ];
    }

    /**
     * Сформировать блок инсайтов.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $deadPages
     * @param  list<array<string, mixed>>  $orphanPages
     * @param  list<array<string, mixed>>  $redirectingPages
     * @param  list<array<string, mixed>>  $canonicalIssues
     * @return list<array{type: string, message: string}>
     */
    protected function buildInsights(
        array $data,
        float $coverage,
        bool $crawlLimited,
        bool $hasDeepPages,
        int $maxCrawlDepth,
        int $totalIssues,
        array $deadPages,
        array $orphanPages,
        array $redirectingPages,
        array $canonicalIssues,
    ): array {
        $insights = [];

        if (! ($data['has_sitemap'] ?? false)) {
            $insights[] = ['type' => 'danger', 'message' => 'Sitemap не найден — поисковые системы не могут эффективно индексировать сайт'];
        }

        if ($crawlLimited) {
            $insights[] = ['type' => 'warning', 'message' => 'Обход был ограничен лимитом страниц — результаты могут быть неполными. Увеличьте max_crawl_pages в настройках теста'];
        }

        if ($hasDeepPages) {
            $insights[] = ['type' => 'warning', 'message' => "Обнаружены страницы глубже {$maxCrawlDepth} уровней — поисковые роботы хуже их индексируют. Улучшите перелинковку или добавьте их в sitemap"];
        }

        if ($coverage < 30 && $coverage > 0) {
            $insights[] = ['type' => 'danger', 'message' => "Только {$coverage}% страниц из sitemap доступны при обходе. Возможные причины: битая навигация, «orphan»-страницы, устаревший sitemap"];
        } elseif ($coverage < 80 && $coverage > 0) {
            $insights[] = ['type' => 'warning', 'message' => "Покрытие {$coverage}% — часть страниц из sitemap недоступна через навигацию"];
        }

        if (! empty($deadPages)) {
            $insights[] = ['type' => 'danger', 'message' => 'Обнаружены мёртвые страницы из sitemap — удалите их из карты сайта или восстановите'];
        }

        if (! empty($orphanPages)) {
            $insights[] = ['type' => 'warning', 'message' => 'Найдены страницы, отсутствующие в sitemap — добавьте их для лучшей индексации'];
        }

        if (! empty($redirectingPages)) {
            $insights[] = ['type' => 'warning', 'message' => 'В sitemap есть URL с редиректами — замените на конечные адреса'];
        }

        if (! empty($canonicalIssues)) {
            $insights[] = ['type' => 'warning', 'message' => 'Обнаружены страницы с несовпадающим canonical — это может приводить к дублям в поиске'];
        }

        if ($totalIssues === 0 && ($data['has_sitemap'] ?? false)) {
            $insights[] = ['type' => 'success', 'message' => 'Всё отлично! Sitemap и структура сайта согласованы'];
        }

        return $insights;
    }
}
