<?php

namespace App\Tests;

use App\Models\Site;
use App\Models\TestResult;

interface TestInterface
{
    /**
     * Запустить тест для сайта
     */
    public function run(Site $site): TestResult;

    /**
     * Получить тип теста
     */
    public function getType(): string;

    /**
     * Получить название теста
     */
    public function getName(): string;

    /**
     * Получить интервал проверки в минутах по умолчанию
     */
    public function getDefaultInterval(): int;
}
