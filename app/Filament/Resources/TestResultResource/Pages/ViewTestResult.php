<?php

namespace App\Filament\Resources\TestResultResource\Pages;

use App\Filament\Resources\TestResultResource;
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
     *
     * @return array<string, mixed>
     */
    protected function computeSitemapMetrics(): array
    {
        $data = $this->record->value ?? [];

        $sitemapCount = $data['sitemap_urls_count'] ?? 0;
        $crawledCount = $data['crawled_urls_count'] ?? 0;
        $deadPages = $data['dead_pages'] ?? [];
        $missingFromSitemap = $data['missing_from_sitemap'] ?? [];
        $redirecting = $data['redirecting_in_sitemap'] ?? [];
        $non200 = $data['non_200_pages'] ?? [];
        $canonicalIssues = $data['canonical_issues'] ?? [];
        $hasSitemap = $data['has_sitemap'] ?? false;
        $crawlLimited = $data['crawl_limited'] ?? false;

        $coverage = $sitemapCount > 0
            ? round(($crawledCount / $sitemapCount) * 100, 1)
            : 0;

        $coverageLevel = match (true) {
            $coverage >= 80 => 'success',
            $coverage >= 30 => 'warning',
            default => 'danger',
        };

        $brokenPages = array_merge(
            array_map(fn (string $url): array => ['url' => $url, 'status' => 'не найдена'], $deadPages),
            array_map(fn (int $code, string $url): array => ['url' => $url, 'status' => (string) $code], $non200, array_keys($non200)),
        );

        $totalIssues = count($deadPages) + count($non200) + count($redirecting) + count($missingFromSitemap) + count($canonicalIssues);

        $healthScore = max(0, min(100,
            100
            - count($deadPages) * 2
            - count($non200) * 3
            - count($redirecting) * 1
            - count($missingFromSitemap) * 1
            - count($canonicalIssues) * 1
        ));

        $healthLevel = match (true) {
            $healthScore >= 80 => 'success',
            $healthScore >= 50 => 'warning',
            default => 'danger',
        };

        $insights = $this->buildInsights($data, $coverage, $crawlLimited, $totalIssues);

        return [
            'coverage' => $coverage,
            'coverageLevel' => $coverageLevel,
            'brokenPages' => $brokenPages,
            'totalIssues' => $totalIssues,
            'healthScore' => $healthScore,
            'healthLevel' => $healthLevel,
            'insights' => $insights,
            'hasSitemap' => $hasSitemap,
            'crawlLimited' => $crawlLimited,
            'deadPages' => $deadPages,
            'missingFromSitemap' => $missingFromSitemap,
            'redirecting' => $redirecting,
            'non200' => $non200,
            'canonicalIssues' => $canonicalIssues,
            'sitemapCount' => $sitemapCount,
            'crawledCount' => $crawledCount,
        ];
    }

    /**
     * Сформировать блок инсайтов.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{type: string, message: string}>
     */
    protected function buildInsights(array $data, float $coverage, bool $crawlLimited, int $totalIssues): array
    {
        $insights = [];

        if (! ($data['has_sitemap'] ?? false)) {
            $insights[] = ['type' => 'danger', 'message' => 'Sitemap не найден — поисковые системы не могут эффективно индексировать сайт'];
        }

        if ($crawlLimited) {
            $insights[] = ['type' => 'warning', 'message' => 'Обход был ограничен лимитом страниц — результаты могут быть неполными. Увеличьте max_crawl_pages в настройках теста'];
        }

        if ($coverage < 30 && $coverage > 0) {
            $insights[] = ['type' => 'danger', 'message' => "Только {$coverage}% страниц из sitemap доступны при обходе. Возможные причины: битая навигация, «orphan»-страницы, устаревший sitemap"];
        } elseif ($coverage < 80 && $coverage > 0) {
            $insights[] = ['type' => 'warning', 'message' => "Покрытие {$coverage}% — часть страниц из sitemap недоступна через навигацию"];
        }

        if (count($data['dead_pages'] ?? []) > 0) {
            $insights[] = ['type' => 'danger', 'message' => 'Обнаружены мёртвые страницы из sitemap — удалите их из карты сайта или восстановите'];
        }

        if (count($data['missing_from_sitemap'] ?? []) > 0) {
            $insights[] = ['type' => 'warning', 'message' => 'Найдены страницы, отсутствующие в sitemap — добавьте их для лучшей индексации'];
        }

        if (count($data['redirecting_in_sitemap'] ?? []) > 0) {
            $insights[] = ['type' => 'warning', 'message' => 'В sitemap есть URL с редиректами — замените на конечные адреса'];
        }

        if (count($data['canonical_issues'] ?? []) > 0) {
            $insights[] = ['type' => 'warning', 'message' => 'Обнаружены страницы с несовпадающим canonical — это может приводить к дублям в поиске'];
        }

        if ($totalIssues === 0 && ($data['has_sitemap'] ?? false)) {
            $insights[] = ['type' => 'success', 'message' => 'Всё отлично! Sitemap и структура сайта согласованы'];
        }

        return $insights;
    }
}
