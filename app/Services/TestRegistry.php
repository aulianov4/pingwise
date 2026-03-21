<?php

namespace App\Services;

use App\Tests\TestInterface;

/**
 * Реестр тестов (SRP).
 * Единственная ответственность — хранение и предоставление зарегистрированных тестов.
 * Тесты инжектируются через конструктор (DIP), не создаются через new (OCP).
 */
class TestRegistry
{
    /**
     * @var array<string, TestInterface>
     */
    protected array $tests = [];

    /**
     * @param  iterable<TestInterface>  $tests
     */
    public function __construct(iterable $tests = [])
    {
        foreach ($tests as $test) {
            $this->register($test);
        }
    }

    /**
     * Зарегистрировать тест
     */
    public function register(TestInterface $test): void
    {
        $this->tests[$test->getType()] = $test;
    }

    /**
     * Получить тест по типу
     */
    public function get(string $testType): ?TestInterface
    {
        return $this->tests[$testType] ?? null;
    }

    /**
     * Получить все зарегистрированные тесты
     *
     * @return array<string, TestInterface>
     */
    public function all(): array
    {
        return $this->tests;
    }
}
