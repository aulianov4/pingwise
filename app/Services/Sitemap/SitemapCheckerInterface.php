<?php

namespace App\Services\Sitemap;

/**
 * Интерфейс пакетной проверки URL (DIP).
 * Позволяет подменять реализацию в тестах.
 *
 * @phpstan-type UrlCheckResult array{
 *     url: string,
 *     status_code: int,
 *     redirect_target: ?string,
 * }
 */
interface SitemapCheckerInterface
{
    /**
     * Проверить список URL параллельными HEAD-запросами.
     *
     * @param  list<string>  $urls
     * @return list<array{url: string, status_code: int, redirect_target: ?string}>
     */
    public function checkUrls(array $urls, int $concurrency = 10): array;
}
