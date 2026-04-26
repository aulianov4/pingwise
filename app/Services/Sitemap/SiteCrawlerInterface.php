<?php

namespace App\Services\Sitemap;

/**
 * Интерфейс краулера сайта (DIP).
 * Позволяет подменять реализацию в тестах.
 *
 * @phpstan-type CrawlPageResult array{
 *     url: string,
 *     status_code: int,
 *     canonical: ?string,
 *     redirect_target: ?string,
 *     depth: int,
 * }
 * @phpstan-type CrawlResult array{
 *     pages: list<CrawlPageResult>,
 *     crawled_count: int,
 *     crawl_limited: bool,
 *     has_deep_pages: bool,
 *     max_crawl_depth: int,
 * }
 */
interface SiteCrawlerInterface
{
    /**
     * Обойти сайт от стартового URL, следуя по внутренним ссылкам.
     *
     * @return array{pages: list<array{url: string, status_code: int, canonical: ?string, redirect_target: ?string, depth: int}>, crawled_count: int, crawl_limited: bool, has_deep_pages: bool, max_crawl_depth: int}
     */
    public function crawl(string $startUrl, int $maxPages = 100, int $timeoutSeconds = 30, int $maxDepth = 5): array;
}
