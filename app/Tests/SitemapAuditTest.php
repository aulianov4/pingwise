<?php

namespace App\Tests;

use App\Models\Site;
use App\Services\Sitemap\SiteCrawlerInterface;
use App\Services\Sitemap\SitemapCheckerInterface;
use App\Services\Sitemap\SitemapParserInterface;
use App\Services\Sitemap\UrlNormalizer;

/**
 * Аудит карты сайта (SRP).
 *
 * Сравнивает sitemap.xml с реальной структурой сайта.
 * Этап 1: парсинг sitemap → список URL.
 * Этап 2: параллельная проверка всех URL из sitemap через HEAD-запросы (SitemapChecker).
 * Этап 3: BFS-обход навигации для поиска страниц, отсутствующих в sitemap (SiteCrawler).
 * Этап 4: сравнение и формирование отчёта.
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

        $baseUrl = rtrim($site->url, '/');
        $sitemapUrl = $baseUrl.$sitemapPath;

        // 1. Парсим sitemap
        $sitemapResult = $this->sitemapParser->parse($sitemapUrl);

        // Оригинальные URL для HEAD-проверки (сохраняют trailing slash из sitemap)
        $rawSitemapUrls = array_values(array_unique($sitemapResult['urls']));

        // Нормализованные URL для сравнения с краулером
        $normalizedSitemapUrls = $this->normalizeUrlSet($sitemapResult['urls']);

        // 2. Проверяем ОРИГИНАЛЬНЫЕ URL из sitemap параллельными HEAD-запросами,
        //    чтобы избежать ложных редиректов из-за удалённого trailing slash
        $checkResults = $this->sitemapChecker->checkUrls($rawSitemapUrls, $concurrency);

        // Маппинг: нормализованный URL → результат проверки
        $checkedMap = [];
        foreach ($checkResults as $result) {
            $checkedMap[UrlNormalizer::normalize($result['url'])] = $result;
        }

        // 3. BFS-обход навигации для поиска страниц, отсутствующих в sitemap
        $crawlResult = $this->siteCrawler->crawl($baseUrl, $maxCrawlPages, $crawlTimeout);
        $crawledPages = $crawlResult['pages'];

        $crawledMap = [];
        foreach ($crawledPages as $page) {
            $crawledMap[$page['url']] = $page;
        }
        $crawledUrls = array_keys($crawledMap);

        // 4. Анализ URL из sitemap (по данным HEAD-проверки)
        $deadPages = [];
        $redirectingInSitemap = [];
        $non200Pages = [];

        foreach ($normalizedSitemapUrls as $url) {
            if (isset($checkedMap[$url])) {
                $check = $checkedMap[$url];

                if ($check['status_code'] === 0) {
                    $deadPages[] = $url;
                } elseif ($check['status_code'] >= 300 && $check['status_code'] < 400) {
                    $redirectingInSitemap[] = $url;
                } elseif ($check['redirect_target'] !== null && $check['redirect_target'] !== $url) {
                    // Реальный редирект: цель отличается от исходного URL
                    // (trailing-slash редиректы игнорируются — после нормализации URL совпадают)
                    $redirectingInSitemap[] = $url;
                } elseif ($check['status_code'] !== 200) {
                    $non200Pages[$url] = $check['status_code'];
                }
            } else {
                $deadPages[] = $url;
            }
        }

        // 5. Страницы на сайте, но не в sitemap
        $missingFromSitemap = array_values(array_diff($crawledUrls, $normalizedSitemapUrls));

        // 6. Canonical-проблемы (из BFS-обхода — там есть HTML)
        $canonicalIssues = [];
        foreach ($crawledPages as $page) {
            if ($page['canonical'] !== null && $page['canonical'] !== $page['url']) {
                $canonicalIssues[] = $page['url'];
            }
        }

        // 7. Дополнительные не-200 из BFS-обхода
        foreach ($crawledPages as $page) {
            if ($page['status_code'] !== 200 && $page['status_code'] !== 0 && ! isset($non200Pages[$page['url']])) {
                if ($page['status_code'] < 300 || $page['status_code'] >= 400) {
                    $non200Pages[$page['url']] = $page['status_code'];
                }
            }
        }

        $value = [
            'sitemap_urls_count' => count($normalizedSitemapUrls),
            'crawled_urls_count' => $crawlResult['crawled_count'],
            'checked_urls_count' => count($checkResults),
            'dead_pages' => array_values(array_unique($deadPages)),
            'missing_from_sitemap' => $missingFromSitemap,
            'redirecting_in_sitemap' => array_values(array_unique($redirectingInSitemap)),
            'non_200_pages' => $non200Pages,
            'canonical_issues' => array_values(array_unique($canonicalIssues)),
            'has_sitemap' => $sitemapResult['has_sitemap'],
            'crawl_limited' => $crawlResult['crawl_limited'],
            'sitemap_parse_errors' => $sitemapResult['errors'],
        ];

        $totalIssues = count($value['dead_pages'])
            + count($value['non_200_pages'])
            + count($value['redirecting_in_sitemap'])
            + count($value['missing_from_sitemap'])
            + count($value['canonical_issues']);

        $status = $this->determineAuditStatus($value, $totalIssues);
        $message = $this->buildMessage($value, $totalIssues);

        return [
            'status' => $status,
            'value' => $value,
            'message' => $message,
        ];
    }

    /**
     * Определить статус аудита.
     *
     * @param  array<string, mixed>  $value
     */
    protected function determineAuditStatus(array $value, int $totalIssues): string
    {
        if (! $value['has_sitemap']) {
            return 'failed';
        }

        $hasCritical = ! empty($value['dead_pages']) || ! empty($value['non_200_pages']);

        if ($hasCritical) {
            return 'failed';
        }

        if ($totalIssues > 0) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Сформировать человекочитаемое сообщение.
     *
     * @param  array<string, mixed>  $value
     */
    protected function buildMessage(array $value, int $totalIssues): string
    {
        if (! $value['has_sitemap']) {
            return 'Sitemap не найден или недоступен';
        }

        if ($totalIssues === 0) {
            return "Аудит пройден. Sitemap: {$value['sitemap_urls_count']} URL, обход: {$value['crawled_urls_count']} страниц";
        }

        $parts = [];

        if (! empty($value['dead_pages'])) {
            $parts[] = 'мёртвых страниц: '.count($value['dead_pages']);
        }

        if (! empty($value['non_200_pages'])) {
            $parts[] = 'битых страниц: '.count($value['non_200_pages']);
        }

        if (! empty($value['redirecting_in_sitemap'])) {
            $parts[] = 'редиректов в sitemap: '.count($value['redirecting_in_sitemap']);
        }

        if (! empty($value['missing_from_sitemap'])) {
            $parts[] = 'не в sitemap: '.count($value['missing_from_sitemap']);
        }

        if (! empty($value['canonical_issues'])) {
            $parts[] = 'canonical-проблем: '.count($value['canonical_issues']);
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
