<?php

namespace App\Tests;

use App\DTO\TestResultData;
use App\Models\Site;
use Illuminate\Support\Facades\Log;

/**
 * Базовый класс теста (SRP).
 * Отвечает ТОЛЬКО за выполнение теста и возврат DTO.
 * Сохранение результата — ответственность TestRunner.
 */
abstract class BaseTest implements TestInterface
{
    /**
     * Запустить тест для сайта — возвращает DTO без сохранения в БД.
     */
    public function run(Site $site): TestResultData
    {
        try {
            $result = $this->execute($site);

            return new TestResultData(
                status: $result['status'],
                value: $result['value'] ?? null,
                message: $result['message'] ?? null,
            );
        } catch (\Exception $e) {
            Log::error("Test {$this->getType()} failed for site {$site->id}: " . $e->getMessage());

            return new TestResultData(
                status: 'failed',
                value: null,
                message: 'Ошибка выполнения теста: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Выполнить конкретную логику теста
     *
     * @return array{status: string, value?: array, message?: string}
     */
    abstract protected function execute(Site $site): array;

    /**
     * Определить статус на основе результата
     */
    protected function determineStatus(bool $isSuccess, ?bool $isWarning = null): string
    {
        if (!$isSuccess) {
            return 'failed';
        }

        if ($isWarning === true) {
            return 'warning';
        }

        return 'success';
    }
}
