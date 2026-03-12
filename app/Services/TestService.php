<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteTest;
use App\Models\TestResult;
use App\Tests\AvailabilityTest;
use App\Tests\DomainTest;
use App\Tests\SslTest;
use App\Tests\TestInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TestService
{
    /**
     * Зарегистрированные тесты
     */
    protected array $tests = [];

    public function __construct()
    {
        $this->registerDefaultTests();
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
    public function getTest(string $testType): ?TestInterface
    {
        return $this->tests[$testType] ?? null;
    }

    /**
     * Получить все зарегистрированные тесты
     */
    public function getAllTests(): array
    {
        return $this->tests;
    }

    /**
     * Запустить проверку для сайта
     */
    public function runTest(Site $site, string $testType): ?TestResult
    {
        $test = $this->getTest($testType);
        
        if (!$test) {
            return null;
        }
        
        $result = $test->run($site);
        
        // Логируем результат теста
        Log::info("Test result: site={$site->id} ({$site->name}), test={$testType}, status={$result->status}, message={$result->message}");
        
        return $result;
    }

    /**
     * Проверить, нужно ли запускать тест для сайта
     */
    public function shouldRunTest(Site $site, string $testType, ?\App\Models\SiteTest $siteTest = null): bool
    {
        // Если siteTest не передан, получаем его
        if (!$siteTest) {
            $siteTest = $site->getTestConfig($testType);
        }
        
        // Проверяем, включен ли тест для сайта
        if (!$siteTest || !$siteTest->is_enabled) {
            return false;
        }

        // Проверяем, прошло ли достаточно времени с последней проверки
        $lastResult = TestResult::where('site_id', $site->id)
            ->where('test_type', $testType)
            ->latest('checked_at')
            ->first();

        if (!$lastResult) {
            return true; // Никогда не проверяли - нужно проверить
        }

        $intervalMinutes = $siteTest->getIntervalMinutes();
        $lastCheckedAt = $lastResult->checked_at;
        $nextCheckAt = $lastCheckedAt->copy()->addMinutes($intervalMinutes);
        
        return now()->greaterThanOrEqualTo($nextCheckAt);
    }

    /**
     * Запустить все необходимые проверки для всех активных сайтов
     */
    public function runScheduledTests(): Collection
    {
        $results = collect();
        
        $sites = Site::where('is_active', true)->get();
        
        foreach ($sites as $site) {
            $siteTestsCount = \App\Models\SiteTest::where('site_id', $site->id)->count();
            
            // Если тесты не инициализированы, инициализируем их
            if ($siteTestsCount === 0) {
                $this->initializeTestsForSite($site);
                $site->refresh();
            }
            
            foreach ($this->tests as $testType => $test) {
                $testConfig = \App\Models\SiteTest::where('site_id', $site->id)
                    ->where('test_type', $testType)
                    ->first();
                
                if ($testConfig && $this->shouldRunTest($site, $testType, $testConfig)) {
                    $result = $this->runTest($site, $testType);
                    if ($result) {
                        $results->push($result);
                    }
                }
            }
        }
        
        return $results;
    }

    /**
     * Инициализировать тесты для сайта (создать записи в site_tests)
     */
    public function initializeTestsForSite(Site $site): void
    {
        foreach ($this->tests as $testType => $test) {
            SiteTest::firstOrCreate(
                [
                    'site_id' => $site->id,
                    'test_type' => $testType,
                ],
                [
                    'is_enabled' => true,
                    'settings' => [
                        'interval_minutes' => $test->getDefaultInterval(),
                    ],
                ]
            );
        }
    }

    /**
     * Зарегистрировать тесты по умолчанию
     */
    protected function registerDefaultTests(): void
    {
        $this->register(new AvailabilityTest());
        $this->register(new SslTest());
        $this->register(new DomainTest());
    }
}
