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
 * }
 * @phpstan-type CrawlResult array{
 *     pages: list<CrawlPageResult>,
 *     crawled_count: int,
 *     crawl_limited: bool,
 * }
 */
interface SiteCrawlerInterface
{
    /**
     * Обойти сайт от стартового URL, следуя по внутренним ссылкам.
     *
     * @return array{pages: list<array{url: string, status_code: int, canonical: ?string, redirect_target: ?string}>, crawled_count: int, crawl_limited: bool}
     */
    public function crawl(string $startUrl, int $maxPages = 100, int $timeoutSeconds = 30): array;
}
