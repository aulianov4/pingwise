<?php

namespace App\Tests;

use App\Models\Site;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AvailabilityTest extends BaseTest
{
    public function getType(): string
    {
        return 'availability';
    }

    public function getName(): string
    {
        return 'Доступность сайта';
    }

    public function getDefaultInterval(): int
    {
        return 5;
    }

    protected function execute(Site $site): array
    {
        $startTime = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => false,
                ])
                ->get($site->url);
        } catch (ConnectionException $e) {
            $responseTime = (int) round((microtime(true) - $startTime) * 1000);

            return [
                'status' => 'failed',
                'value' => [
                    'status_code' => null,
                    'response_time_ms' => $responseTime,
                    'is_up' => false,
                    'error' => 'connection_error',
                ],
                'message' => 'Ошибка подключения: '.$e->getMessage(),
            ];
        }

        $responseTime = (int) round((microtime(true) - $startTime) * 1000);
        $statusCode = $response->status();
        $isUp = $statusCode >= 200 && $statusCode < 400;

        $message = $isUp
            ? "Сайт доступен. Код ответа: {$statusCode}, время отклика: {$responseTime} мс"
            : "Сайт недоступен. Код ответа: {$statusCode}, время отклика: {$responseTime} мс";

        return [
            'status' => $this->determineStatus($isUp),
            'value' => [
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
                'is_up' => $isUp,
            ],
            'message' => $message,
        ];
    }
}
