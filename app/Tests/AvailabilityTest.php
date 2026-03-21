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

    protected function execute(Site $site): array
    {
        $attempts = 1;
        $startTime = microtime(true);

        try {
            $response = Http::retry(self::MAX_ATTEMPTS, self::RETRY_DELAY_MS, function (\Exception $e) use (&$attempts): bool {
                $attempts++;

                return $e instanceof ConnectionException;
            })
                ->timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => false,
                ])
                ->get($site->url);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            $statusCode = $response->status();
            $isUp = $statusCode >= 200 && $statusCode < 400;

            $message = $isUp
                ? "Сайт доступен. Код ответа: {$statusCode}, время отклика: {$responseTime}мс"
                : "Сайт недоступен. Код ответа: {$statusCode}";

            if ($attempts > 1) {
                $message .= " (попытка {$attempts} из ".self::MAX_ATTEMPTS.')';
            }

            return [
                'status' => $this->determineStatus($isUp),
                'value' => [
                    'status_code' => $statusCode,
                    'response_time_ms' => $responseTime,
                    'is_up' => $isUp,
                    'attempts' => $attempts,
                ],
                'message' => $message,
            ];
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            $isConnectionError = $e instanceof ConnectionException;

            return [
                'status' => 'failed',
                'value' => [
                    'status_code' => null,
                    'response_time_ms' => $responseTime,
                    'is_up' => false,
                    'error' => $isConnectionError ? 'connection_error' : 'unknown_error',
                    'attempts' => $attempts,
                ],
                'message' => $isConnectionError
                    ? "Ошибка подключения после {$attempts} попыток: ".$e->getMessage()
                    : 'Ошибка при проверке доступности: '.$e->getMessage(),
            ];
        }
    }
}
