<?php

namespace App\Services\Sitemap;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;

/**
 * Пакетная проверка URL через параллельные HEAD-запросы (SRP).
 * Использует Guzzle Pool для контролируемой конкурентности.
 * Не загружает тело страницы — только статус и заголовки.
 */
class SitemapChecker implements SitemapCheckerInterface
{
    /**
     * {@inheritDoc}
     */
    public function checkUrls(array $urls, int $concurrency = 10): array
    {
        if (empty($urls)) {
            return [];
        }
        $client = new Client([
            'verify' => false,
            'timeout' => 10,
            'connect_timeout' => 5,
            'allow_redirects' => [
                'max' => 5,
                'track_redirects' => true,
            ],
        ]);
        $results = [];
        $requests = function () use ($urls) {
            foreach ($urls as $index => $url) {
                yield $index => new Request('HEAD', $url);
            }
        };
        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function (Response $response, int $index) use ($urls, &$results): void {
                $redirectTarget = null;
                $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
                if (! empty($redirectHistory)) {
                    $redirectTarget = UrlNormalizer::normalize(end($redirectHistory));
                }
                $results[$index] = [
                    'url' => $urls[$index],
                    'status_code' => $response->getStatusCode(),
                    'redirect_target' => $redirectTarget,
                ];
            },
            'rejected' => function (RequestException|ConnectException|\Throwable $reason, int $index) use ($urls, &$results): void {
                $statusCode = 0;
                if ($reason instanceof RequestException && $reason->hasResponse()) {
                    $statusCode = $reason->getResponse()->getStatusCode();
                }
                Log::debug("SitemapChecker: error for {$urls[$index]}: {$reason->getMessage()}");
                $results[$index] = [
                    'url' => $urls[$index],
                    'status_code' => $statusCode,
                    'redirect_target' => null,
                ];
            },
        ]);
        $pool->promise()->wait();
        ksort($results);

        return array_values($results);
    }
}
