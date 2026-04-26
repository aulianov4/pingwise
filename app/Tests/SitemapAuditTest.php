<?php

namespace App\Tests;

use App\Models\AuditPage;
use App\Models\Site;
use App\Services\Sitemap\SiteCrawlerInterface;
use App\Services\Sitemap\SitemapCheckerInterface;
use App\Services\Sitemap\SitemapParserInterface;
use App\Services\Sitemap\UrlNormalizer;
use Illuminate\Support\Carbon;

/**
 * Аудит карты сайта (SRP).
 *
 * Сравнивает sitemap.xml с реальной структурой сайта.
 * Этап 1: парсинг sitemap → список URL.
 * Этап 2: параллельная проверка всех URL из sitemap через HEAD-запросы (SitemapChecker).
 * Этап 3: BFS-обход навигации для поиска страниц, отсутствующих в sitemap (SiteCrawler).
 * Этап 4: upsert результатов в audit_pages (текущее состояние URL).
 * Этап 5: формирование агрегатного отчёта.
 *
 * Зависит от абстракций SitemapParserInterface, SitemapCheckerInterface и SiteCrawlerInterface (DIP).
 */
class SitemapAuditTest extends BaseTest
{
    public function __construct(
        protected readonly SitemapParserInterface $sitemapParser,
        protected readonly SitemapCheckerInterface $sitemapChecker,
        protected readonly SiteCrawlerInterface $siteCrawler,
    ) {}

    public function getType(): string
    {
        return 'sitemap';
    }

    public function getName(): string
    {
        return 'Аудит карты сайта';
    }

    public function getDefaultInterval(): int
    {
        return 24 * 60;
    }

    protected function execute(Site $site): array
    {
        $siteTest = $site->getTestConfig($this->getType());
        $settings = $siteTest?->settings ?? [];

        $maxCrawlPages = $settings['max_crawl_pages'] ?? 5000;
        $crawlTimeout = $settings['crawl_timeout_seconds'] ?? 300;
        $sitemapPath = $settings['sitemap_url'] ?? '/sitemap.xml';
        $concurrency = $settings['check_concurrency'] ?? 10;
        $maxDepth = $settings['max_crawl_depth'] ?? 5;

        $baseUrl = rtrim($site->url, '/');
        $sitemapUrl = $baseUrl.$sitemapPath;

        // 0. Снимок текущего состояния (для вычисления diff)
        $isFirstRun = ! AuditPage::where('site_id', $site->id)->exists();
        $prevSitemapUrls = $isFirstRun
            ? []
            : AuditPage::where('site_id', $site->id)->where('in_sitemap', true)->pluck('url')->all();
        $prevCrawlUrls = $isFirstRun
            ? []
            : AuditPage::where('site_id', $site->id)->where('in_crawl', true)->pluck('url')->all();

        // 1. Парсим sitemap
        $sitemapResult = $this->sitemapParser->parse($sitemapUrl);

        // Оригинальные URL для HEAD-проверки (сохраняют trailing slash из sitemap)
        $rawSitemapUrls = array_values(array_unique($sitemapResult['urls']));

        // Нормализованные URL для сравнения с краулером
        $normalizedSitemapUrls = $this->normalizeUrlSet($sitemapResult['urls']);

        // 2. Проверяем ОРИГИНАЛЬНЫЕ URL из sitemap параллельными HEAD-запросами
        $checkResults = $this->sitemapChecker->checkUrls($rawSitemapUrls, $concurrency);

        // Маппинг: нормализованный URL → результат проверки
        $checkedMap = [];
        foreach ($checkResults as $result) {
            $checkedMap[UrlNormalizer::normalize($result['url'])] = $result;
        }

        // 3. BFS-обход навигации для поиска страниц, отсутствующих в sitemap
        $crawlResult = $this->siteCrawler->crawl($baseUrl, $maxCrawlPages, $crawlTimeout, $maxDepth);
        $crawledPages = $crawlResult['pages'];

        // 4. Строим сводную карту всех URL и их состояний
        $runAt = now();
        $allPageData = $this->buildPageData(
            $normalizedSitemapUrls,
            $checkedMap,
            $crawledPages,
        );

        // 5. Вычисляем diff до upsert
        $currentSitemapSet = array_flip($normalizedSitemapUrls);
        $currentCrawlSet = array_flip(array_column($crawledPages, 'url'));
        $prevSitemapSet = array_flip($prevSitemapUrls);
        $prevCrawlSet = array_flip($prevCrawlUrls);

        $newInSitemap = array_diff_key($currentSitemapSet, $prevSitemapSet);
        $removedFromSitemap = array_diff_key($prevSitemapSet, $currentSitemapSet);
        $newInCrawl = array_diff_key($currentCrawlSet, $prevCrawlSet);
        $removedFromCrawl = array_diff_key($prevCrawlSet, $currentCrawlSet);

        // 6. Сохраняем состояние URL в audit_pages (upsert)
        $this->persistAuditPages($site, $allPageData, $normalizedSitemapUrls, $runAt);

        // 7. Обновляем removed_from_sitemap_at для удалённых URL
        if (! empty($removedFromSitemap)) {
            foreach (array_chunk(array_keys($removedFromSitemap), 500) as $chunk) {
                AuditPage::where('site_id', $site->id)
                    ->whereIn('url', $chunk)
                    ->update(['removed_from_sitemap_at' => $runAt]);
            }
        }

        // 8. Считаем агрегаты из собранных данных
        $counts = $this->computeCounts($normalizedSitemapUrls, $checkedMap, $crawledPages);

        // Корректное покрытие: % URL из sitemap, найденных краулером
        $coverage = count($normalizedSitemapUrls) > 0
            ? round(count(array_intersect_key($currentSitemapSet, $currentCrawlSet)) / count($normalizedSitemapUrls) * 100, 1)
            : 0;

        $value = [
            'sitemap_urls_count' => count($normalizedSitemapUrls),
            'crawled_urls_count' => $crawlResult['crawled_count'],
            'checked_urls_count' => count($checkResults),
            'dead_count' => $counts['dead'],
            'non_200_count' => $counts['non_200'],
            'redirect_count' => $counts['redirect'],
            'missing_count' => $counts['missing'],
            'canonical_count' => $counts['canonical'],
            'has_sitemap' => $sitemapResult['has_sitemap'],
            'crawl_limited' => $crawlResult['crawl_limited'],
            'has_deep_pages' => $crawlResult['has_deep_pages'],
            'max_crawl_depth' => $crawlResult['max_crawl_depth'],
            'sitemap_parse_errors' => $sitemapResult['errors'],
            'coverage' => $coverage,
            'new_sitemap_count' => $isFirstRun ? 0 : count($newInSitemap),
            'removed_sitemap_count' => $isFirstRun ? 0 : count($removedFromSitemap),
            'new_crawl_count' => $isFirstRun ? 0 : count($newInCrawl),
            'removed_crawl_count' => $isFirstRun ? 0 : count($removedFromCrawl),
            'is_first_run' => $isFirstRun,
        ];

        $totalIssues = $counts['dead'] + $counts['non_200'] + $counts['redirect']
            + $counts['missing'] + $counts['canonical'];

        $status = $this->determineAuditStatus($value, $counts);
        $message = $this->buildMessage($value, $counts, $totalIssues);

        return [
            'status' => $status,
            'value' => $value,
            'message' => $message,
        ];
    }

    /**
     * Собрать сводную карту всех URL и их данных (sitemap + crawl).
     *
     * @param  list<string>  $normalizedSitemapUrls
     * @param  array<string, array{url: string, status_code: int, redirect_target: ?string}>  $checkedMap
     * @param  list<array{url: string, status_code: int, canonical: ?string, redirect_target: ?string, depth: int}>  $crawledPages
     * @return array<string, array{url: string, status_code: int, in_sitemap: bool, in_crawl: bool, crawl_depth: ?int, redirect_target: ?string, canonical: ?string}>
     */
    protected function buildPageData(
        array $normalizedSitemapUrls,
        array $checkedMap,
        array $crawledPages,
    ): array {
        $sitemapSet = array_flip($normalizedSitemapUrls);
        $pages = [];

        // Данные из sitemap + HEAD-проверки
        foreach ($normalizedSitemapUrls as $url) {
            $check = $checkedMap[$url] ?? null;
            $pages[$url] = [
                'url' => $url,
                'status_code' => $check['status_code'] ?? 0,
                'in_sitemap' => true,
                'in_crawl' => false,
                'crawl_depth' => null,
                'redirect_target' => $check['redirect_target'] ?? null,
                'canonical' => null,
            ];
        }

        // Данные из краулера — merge или новая запись
        foreach ($crawledPages as $page) {
            $url = $page['url'];

            if (isset($pages[$url])) {
                $pages[$url]['in_crawl'] = true;
                $pages[$url]['crawl_depth'] = $page['depth'];
                $pages[$url]['canonical'] = $page['canonical'];

                // Используем статус краулера, если HEAD-запрос не отвечал
                if ($pages[$url]['status_code'] === 0 && $page['status_code'] !== 0) {
                    $pages[$url]['status_code'] = $page['status_code'];
                }
            } else {
                $pages[$url] = [
                    'url' => $url,
                    'status_code' => $page['status_code'],
                    'in_sitemap' => isset($sitemapSet[$url]),
                    'in_crawl' => true,
                    'crawl_depth' => $page['depth'],
                    'redirect_target' => $page['redirect_target'],
                    'canonical' => $page['canonical'],
                ];
            }
        }

        return $pages;
    }

    /**
     * Upsert страниц в audit_pages и пометить исчезнувшие.
     *
     * @param  array<string, array<string, mixed>>  $allPageData
     * @param  list<string>  $normalizedSitemapUrls
     */
    protected function persistAuditPages(
        Site $site,
        array $allPageData,
        array $normalizedSitemapUrls,
        Carbon $runAt,
    ): void {
        // Сбрасываем флаги присутствия для всех страниц сайта.
        // Upsert восстановит нужные флаги для страниц, найденных в этом прогоне.
        // Страницы, исчезнувшие из sitemap/краулера, автоматически получат false.
        AuditPage::where('site_id', $site->id)
            ->update(['in_sitemap' => false, 'in_crawl' => false, 'updated_at' => $runAt]);

        if (empty($allPageData)) {
            return;
        }

        $rows = [];
        foreach ($allPageData as $pageData) {
            $rows[] = [
                'site_id' => $site->id,
                'url' => $pageData['url'],
                'status_code' => $pageData['status_code'],
                'in_sitemap' => $pageData['in_sitemap'],
                'in_crawl' => $pageData['in_crawl'],
                'crawl_depth' => $pageData['crawl_depth'],
                'redirect_target' => $pageData['redirect_target'],
                'canonical' => $pageData['canonical'],
                'first_seen_at' => $runAt,
                'last_seen_at' => $runAt,
                'last_in_sitemap_at' => $pageData['in_sitemap'] ? $runAt : null,
                'created_at' => $runAt,
                'updated_at' => $runAt,
            ];
        }

        // Upsert: first_seen_at НЕ обновляем при конфликте (хранит дату первого обнаружения)
        foreach (array_chunk($rows, 500) as $chunk) {
            AuditPage::upsert(
                $chunk,
                ['site_id', 'url'],
                ['status_code', 'in_sitemap', 'in_crawl', 'crawl_depth', 'redirect_target', 'canonical', 'last_seen_at', 'updated_at'],
            );
        }

        // Обновляем last_in_sitemap_at для текущих URL sitemap
        foreach (array_chunk($normalizedSitemapUrls, 500) as $chunk) {
            AuditPage::where('site_id', $site->id)
                ->whereIn('url', $chunk)
                ->update(['last_in_sitemap_at' => $runAt]);
        }
    }

    /**
     * Подсчитать количество проблем каждой категории.
     *
     * @param  list<string>  $normalizedSitemapUrls
     * @param  array<string, array{url: string, status_code: int, redirect_target: ?string}>  $checkedMap
     * @param  list<array{url: string, status_code: int, canonical: ?string, redirect_target: ?string, depth: int}>  $crawledPages
     * @return array{dead: int, non_200: int, redirect: int, missing: int, canonical: int}
     */
    protected function computeCounts(
        array $normalizedSitemapUrls,
        array $checkedMap,
        array $crawledPages,
    ): array {
        $crawledUrls = array_column($crawledPages, 'url');

        $dead = 0;
        $non200 = 0;
        $redirect = 0;

        foreach ($normalizedSitemapUrls as $url) {
            $check = $checkedMap[$url] ?? null;
            $code = $check['status_code'] ?? 0;
            $redirectTarget = $check['redirect_target'] ?? null;

            if ($code === 0) {
                $dead++;
            } elseif ($code >= 300 && $code < 400) {
                $redirect++;
            } elseif ($redirectTarget !== null && $redirectTarget !== $url) {
                $redirect++;
            } elseif ($code !== 200) {
                $non200++;
            }
        }

        // Дополнительные не-200 из краулера (не в sitemap)
        foreach ($crawledPages as $page) {
            if (! in_array($page['url'], $normalizedSitemapUrls, true)
                && $page['status_code'] !== 200
                && $page['status_code'] !== 0
                && ($page['status_code'] < 300 || $page['status_code'] >= 400)
            ) {
                $non200++;
            }
        }

        $missing = count(array_diff($crawledUrls, $normalizedSitemapUrls));

        $canonical = 0;
        foreach ($crawledPages as $page) {
            if ($page['canonical'] !== null && $page['canonical'] !== $page['url']) {
                $canonical++;
            }
        }

        return [
            'dead' => $dead,
            'non_200' => $non200,
            'redirect' => $redirect,
            'missing' => $missing,
            'canonical' => $canonical,
        ];
    }

    /**
     * Определить статус аудита.
     *
     * @param  array<string, mixed>  $value
     * @param  array{dead: int, non_200: int, redirect: int, missing: int, canonical: int}  $counts
     */
    protected function determineAuditStatus(array $value, array $counts): string
    {
        if (! $value['has_sitemap']) {
            return 'failed';
        }

        if ($counts['dead'] > 0 || $counts['non_200'] > 0) {
            return 'failed';
        }

        $totalIssues = $counts['dead'] + $counts['non_200'] + $counts['redirect']
            + $counts['missing'] + $counts['canonical'];

        if ($totalIssues > 0) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Сформировать человекочитаемое сообщение.
     *
     * @param  array<string, mixed>  $value
     * @param  array{dead: int, non_200: int, redirect: int, missing: int, canonical: int}  $counts
     */
    protected function buildMessage(array $value, array $counts, int $totalIssues): string
    {
        if (! $value['has_sitemap']) {
            return 'Sitemap не найден или недоступен';
        }

        if ($totalIssues === 0) {
            return "Аудит пройден. Sitemap: {$value['sitemap_urls_count']} URL, обход: {$value['crawled_urls_count']} страниц";
        }

        $parts = [];

        if ($counts['dead'] > 0) {
            $parts[] = 'мёртвых страниц: '.$counts['dead'];
        }

        if ($counts['non_200'] > 0) {
            $parts[] = 'битых страниц: '.$counts['non_200'];
        }

        if ($counts['redirect'] > 0) {
            $parts[] = 'редиректов в sitemap: '.$counts['redirect'];
        }

        if ($counts['missing'] > 0) {
            $parts[] = 'не в sitemap: '.$counts['missing'];
        }

        if ($counts['canonical'] > 0) {
            $parts[] = 'canonical-проблем: '.$counts['canonical'];
        }

        return "Найдено проблем: {$totalIssues}. ".implode(', ', $parts);
    }

    /**
     * Нормализовать и дедуплицировать набор URL.
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    protected function normalizeUrlSet(array $urls): array
    {
        $normalized = [];
        foreach ($urls as $url) {
            $normalized[] = UrlNormalizer::normalize($url);
        }

        return array_values(array_unique($normalized));
    }
}
