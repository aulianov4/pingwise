<?php

namespace App\Tests;

/**
 * Интерфейс метаданных теста (ISP — Interface Segregation Principle).
 * Клиенты, которым нужны только метаданные (UI, реестр),
 * не зависят от метода run().
 */
interface TestMetadataInterface
{
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
