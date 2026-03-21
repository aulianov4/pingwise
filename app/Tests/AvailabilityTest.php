<?php

namespace App\Tests;

use App\Models\Site;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AvailabilityTest extends BaseTest
{
    /**
     * Максимальное количество попыток подключения перед объявлением провала.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Задержка между повторными попытками (мс).
     */
    private const RETRY_DELAY_MS = 500;

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
        return 5; // 5 минут
    }

    /**
     * Коды ответов сервера, при которых выполняется повторная попытка.
     *
     * @var list<int>
     */
    private const RETRYABLE_STATUS_CODES = [500, 502, 503, 504];

    protected function execute(Site $site): array
    {
        $startTime = microtime(true);
        $lastException = null;
        $response = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $lastException = null;
            $response = null;

            if ($attempt > 1) {
                usleep(self::RETRY_DELAY_MS * 1000);
            }

            try {
                $response = Http::timeout(10)
                    ->withOptions([
                        'allow_redirects' => true,
                        'verify' => false,
                    ])
                    ->get($site->url);

                if (! in_array($response->status(), self::RETRYABLE_STATUS_CODES, true)) {
                    break;
                }
            } catch (ConnectionException $e) {
                $lastException = $e;
            }
        }

        $responseTime = round((microtime(true) - $startTime) * 1000);

        if ($lastException !== null) {
            return [
                'status' => 'failed',
                'value' => [
                    'status_code' => null,
                    'response_time_ms' => $responseTime,
                    'is_up' => false,
                    'error' => 'connection_error',
                    'attempts' => $attempt,
                ],
                'message' => "Ошибка подключения после {$attempt} попыток: ".$lastException->getMessage(),
            ];
        }

        $statusCode = $response->status();
        $isUp = $statusCode >= 200 && $statusCode < 400;

        $message = $isUp
            ? "Сайт доступен. Код ответа: {$statusCode}, время отклика: {$responseTime}мс"
            : "Сайт недоступен. Код ответа: {$statusCode}";

        if ($attempt > 1) {
            $message .= " (попытка {$attempt} из ".self::MAX_ATTEMPTS.')';
        }

        return [
            'status' => $this->determineStatus($isUp),
            'value' => [
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
                'is_up' => $isUp,
                'attempts' => $attempt,
            ],
            'message' => $message,
        ];
    }
}
