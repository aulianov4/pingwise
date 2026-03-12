<?php

namespace App\Tests;

use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        return 5; // 5 минут
    }

    protected function execute(Site $site): array
    {
        $startTime = microtime(true);
        
        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => false, // Для тестирования, можно включить проверку SSL
                ])
                ->get($site->url);
            
            $responseTime = round((microtime(true) - $startTime) * 1000); // в миллисекундах
            
            $statusCode = $response->status();
            $isUp = $statusCode >= 200 && $statusCode < 400;
            
            return [
                'status' => $this->determineStatus($isUp),
                'value' => [
                    'status_code' => $statusCode,
                    'response_time_ms' => $responseTime,
                    'is_up' => $isUp,
                ],
                'message' => $isUp 
                    ? "Сайт доступен. Код ответа: {$statusCode}, время отклика: {$responseTime}мс"
                    : "Сайт недоступен. Код ответа: {$statusCode}",
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            
            return [
                'status' => 'failed',
                'value' => [
                    'status_code' => null,
                    'response_time_ms' => $responseTime,
                    'is_up' => false,
                    'error' => 'connection_error',
                ],
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            
            return [
                'status' => 'failed',
                'value' => [
                    'status_code' => null,
                    'response_time_ms' => $responseTime,
                    'is_up' => false,
                    'error' => 'unknown_error',
                ],
                'message' => 'Ошибка при проверке доступности: ' . $e->getMessage(),
            ];
        }
    }
}
