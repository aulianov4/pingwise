<?php

namespace App\Services\Sitemap;

/**
 * Интерфейс парсинга sitemap.xml (DIP).
 * Позволяет подменять реализацию в тестах.
 *
 * @phpstan-type SitemapUrl array{loc: string, lastmod: ?string}
 * @phpstan-type SitemapParseResult array{
 *     urls: list<string>,
 *     has_sitemap: bool,
 *     errors: list<string>,
 * }
 */
interface SitemapParserInterface
{
    /**
     * Спарсить sitemap и вернуть список URL.
     *
     * @return array{urls: list<string>, has_sitemap: bool, errors: list<string>}
     */
    public function parse(string $sitemapUrl): array;
}
