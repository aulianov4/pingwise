<?php

namespace App\Tests;

use App\DTO\TestResultData;
use App\Models\Site;

/**
 * Полный интерфейс теста: метаданные + запуск (ISP).
 * Расширяет TestMetadataInterface, добавляя возможность выполнения.
 * Возвращает DTO вместо Eloquent-модели (DIP, LSP).
 */
interface TestInterface extends TestMetadataInterface
{
    /**
     * Запустить тест для сайта
     */
    public function run(Site $site): TestResultData;
}
