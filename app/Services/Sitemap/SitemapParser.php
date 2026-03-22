<?php

namespace App\Services\Sitemap;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Парсер sitemap.xml с поддержкой sitemapindex (SRP).
 * Рекурсивно обрабатывает вложенные карты сайта.
 */
class SitemapParser implements SitemapParserInterface
{
    /**
     * Максимальная глубина рекурсии для sitemapindex.
     */
    private const MAX_DEPTH = 3;

    /**
     * Максимальное количество URL из sitemap.
     */
    private const MAX_URLS = 10_000;

    /**
     * {@inheritDoc}
     */
    public function parse(string $sitemapUrl): array
    {
        $urls = [];
        $errors = [];
        $hasSitemap = false;

        $this->parseSitemap($sitemapUrl, $urls, $errors, $hasSitemap, depth: 0);

        return [
            'urls' => array_values(array_unique($urls)),
            'has_sitemap' => $hasSitemap,
            'errors' => $errors,
        ];
    }

    /**
     * Рекурсивно парсить sitemap / sitemapindex.
     *
     * @param  list<string>  $urls
     * @param  list<string>  $errors
     */
    protected function parseSitemap(
        string $sitemapUrl,
        array &$urls,
        array &$errors,
        bool &$hasSitemap,
        int $depth,
    ): void {
        if ($depth >= self::MAX_DEPTH) {
            $errors[] = "Превышена максимальная глубина вложенности sitemap ({$sitemapUrl})";

            return;
        }

        if (count($urls) >= self::MAX_URLS) {
            return;
        }

        try {
            $response = Http::timeout(15)->get($sitemapUrl);

            if (! $response->successful()) {
                $errors[] = "Не удалось загрузить {$sitemapUrl}: HTTP {$response->status()}";

                return;
            }

            $hasSitemap = true;
            $body = $response->body();

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                $errors[] = "Не удалось распарсить XML: {$sitemapUrl}";

                return;
            }

            // Убираем namespace для удобства
            $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            // Проверяем, является ли это sitemapindex
            $sitemaps = $xml->xpath('//sm:sitemap/sm:loc') ?: $xml->xpath('//sitemap/loc');
            if (! empty($sitemaps)) {
                foreach ($sitemaps as $loc) {
                    $childUrl = trim((string) $loc);
                    if ($childUrl !== '') {
                        $this->parseSitemap($childUrl, $urls, $errors, $hasSitemap, $depth + 1);
                    }

                    if (count($urls) >= self::MAX_URLS) {
                        return;
                    }
                }

                return;
            }

            // Обычный sitemap — извлекаем URL
            $locs = $xml->xpath('//sm:url/sm:loc') ?: $xml->xpath('//url/loc');
            if (! empty($locs)) {
                foreach ($locs as $loc) {
                    $url = trim((string) $loc);
                    if ($url !== '') {
                        $urls[] = UrlNormalizer::normalize($url);
                    }

                    if (count($urls) >= self::MAX_URLS) {
                        return;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Ошибка парсинга sitemap {$sitemapUrl}: {$e->getMessage()}");
            $errors[] = "Ошибка загрузки {$sitemapUrl}: {$e->getMessage()}";
        }
    }
}
