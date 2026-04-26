<?php

namespace App\Services\Sitemap;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Краулер сайта — BFS-обход по внутренним ссылкам (SRP).
 * Собирает URL, HTTP-статусы, canonical-теги, редиректы.
 * Обходит только страницы того же домена.
 */
class SiteCrawler implements SiteCrawlerInterface
{
    /**
     * Задержка между запросами (мс), чтобы не нагружать сервер.
     */
    private const CRAWL_DELAY_MS = 50;

    /**
     * Расширения файлов, которые пропускаем при краулинге.
     *
     * @var list<string>
     */
    private const SKIP_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'rar', 'tar', 'gz',
        'mp3', 'mp4', 'avi', 'mov', 'wmv',
        'css', 'js', 'json', 'xml', 'woff', 'woff2', 'ttf', 'eot',
    ];

    /**
     * {@inheritDoc}
     */
    public function crawl(string $startUrl, int $maxPages = 100, int $timeoutSeconds = 30, int $maxDepth = 5): array
    {
        $baseHost = parse_url($startUrl, PHP_URL_HOST);

        if (! $baseHost) {
            return [
                'pages' => [],
                'crawled_count' => 0,
                'crawl_limited' => false,
                'has_deep_pages' => false,
                'max_crawl_depth' => 0,
            ];
        }

        $startTime = microtime(true);
        $visited = [];

        // Очередь: ['url' => string, 'depth' => int]
        $queue = [['url' => UrlNormalizer::normalize($startUrl), 'depth' => 0]];
        $pages = [];
        $hasDeepPages = false;
        $maxFoundDepth = 0;

        while (! empty($queue) && count($visited) < $maxPages) {
            if ((microtime(true) - $startTime) >= $timeoutSeconds) {
                break;
            }

            ['url' => $url, 'depth' => $depth] = array_shift($queue);

            if (isset($visited[$url])) {
                continue;
            }

            if (! UrlNormalizer::isSameHost($url, $baseHost)) {
                continue;
            }

            if ($this->shouldSkipUrl($url)) {
                continue;
            }

            $visited[$url] = true;
            $maxFoundDepth = max($maxFoundDepth, $depth);

            $pageResult = $this->fetchPage($url);

            if ($pageResult === null) {
                $pages[] = [
                    'url' => $url,
                    'status_code' => 0,
                    'canonical' => null,
                    'redirect_target' => null,
                    'depth' => $depth,
                ];

                continue;
            }

            $pageResult['depth'] = $depth;
            $pages[] = $pageResult;

            // Извлекаем внутренние ссылки только из успешных HTML-страниц
            if ($pageResult['status_code'] >= 200
                && $pageResult['status_code'] < 400
                && isset($pageResult['links'])
            ) {
                $nextDepth = $depth + 1;

                foreach ($pageResult['links'] as $link) {
                    $normalized = UrlNormalizer::normalize($link);

                    if (isset($visited[$normalized]) || ! UrlNormalizer::isSameHost($normalized, $baseHost)) {
                        continue;
                    }

                    if ($nextDepth > $maxDepth) {
                        // Страницы глубже лимита не обходим, но фиксируем факт их существования
                        $hasDeepPages = true;

                        continue;
                    }

                    $queue[] = ['url' => $normalized, 'depth' => $nextDepth];
                }

                unset($pages[array_key_last($pages)]['links']);
                $pages = array_values($pages);
            }

            // Задержка между запросами
            if (! empty($queue)) {
                usleep(self::CRAWL_DELAY_MS * 1000);
            }
        }

        return [
            'pages' => $pages,
            'crawled_count' => count($pages),
            'crawl_limited' => ! empty($queue) || count($visited) >= $maxPages,
            'has_deep_pages' => $hasDeepPages,
            'max_crawl_depth' => $maxFoundDepth,
        ];
    }

    /**
     * Загрузить страницу и извлечь данные.
     *
     * @return array{url: string, status_code: int, canonical: ?string, redirect_target: ?string, links?: list<string>}|null
     */
    protected function fetchPage(string $url): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'track_redirects' => true,
                    ],
                    'verify' => false,
                ])
                ->get($url);

            $statusCode = $response->status();
            $redirectTarget = null;
            $canonical = null;
            $links = [];

            // Определяем redirect
            $redirectHistory = $response->header('X-Guzzle-Redirect-History');
            if ($redirectHistory) {
                $redirects = is_array($redirectHistory)
                    ? $redirectHistory
                    : explode(', ', $redirectHistory);
                $redirectTarget = UrlNormalizer::normalize(end($redirects));
            }

            // Парсим HTML только если content-type — text/html
            $contentType = $response->header('Content-Type') ?? '';
            if (str_contains($contentType, 'text/html')) {
                $body = $response->body();
                $canonical = $this->extractCanonical($body);
                $links = $this->extractLinks($body, $url);
            }

            $result = [
                'url' => $url,
                'status_code' => $statusCode,
                'canonical' => $canonical,
                'redirect_target' => $redirectTarget,
            ];

            if (! empty($links)) {
                $result['links'] = $links;
            }

            return $result;
        } catch (ConnectionException $e) {
            Log::debug("Краулер: ошибка соединения для {$url}: {$e->getMessage()}");

            return null;
        } catch (\Exception $e) {
            Log::debug("Краулер: ошибка для {$url}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Извлечь canonical URL из HTML.
     */
    protected function extractCanonical(string $html): ?string
    {
        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
            return UrlNormalizer::normalize($matches[1]);
        }

        if (preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\']/i', $html, $matches)) {
            return UrlNormalizer::normalize($matches[1]);
        }

        return null;
    }

    /**
     * Извлечь внутренние ссылки из HTML.
     *
     * @return list<string>
     */
    protected function extractLinks(string $html, string $baseUrl): array
    {
        $links = [];

        if (! preg_match_all('/<a[^>]+href=["\']([^"\'#]+)/i', $html, $matches)) {
            return [];
        }

        $baseHost = parse_url($baseUrl, PHP_URL_HOST);

        foreach ($matches[1] as $href) {
            $href = trim($href);

            if ($href === '' || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
                continue;
            }

            $absoluteUrl = UrlNormalizer::resolveRelative($href, $baseUrl);

            if (UrlNormalizer::isSameHost($absoluteUrl, $baseHost)) {
                $links[] = UrlNormalizer::normalize($absoluteUrl);
            }
        }

        return array_values(array_unique($links));
    }

    /**
     * Проверить, нужно ли пропустить URL (файлы, не HTML).
     */
    protected function shouldSkipUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::SKIP_EXTENSIONS, true);
    }
}
