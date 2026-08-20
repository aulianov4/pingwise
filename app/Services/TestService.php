<?php

namespace App\Services;

use App\Events\TestStatusChanged;
use App\Models\Site;
use App\Models\SiteTest;
use App\Models\TestResult;
use App\Services\Availability\AvailabilityIncidentEvaluator;
use App\Tests\TestInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Сервис запуска тестов (SRP).
 * Ответственность: оркестрация запуска тестов и сохранение результатов.
 * Реестр тестов делегирован TestRegistry (SRP).
 * Зависит от абстракций TestRegistry и Dispatcher (DIP).
 */
class TestService
{
    public function __construct(
        protected readonly TestRegistry $registry,
        protected readonly Dispatcher $events,
        protected readonly AvailabilityIncidentEvaluator $availabilityIncidents,
    ) {}

    /**
     * Получить тест по типу (делегирует реестру)
     */
    public function getTest(string $testType): ?TestInterface
    {
        return $this->registry->get($testType);
    }

    /**
     * Получить все зарегистрированные тесты
     */
    public function getAllTests(): array
    {
        return $this->registry->all();
    }

    /**
     * Запустить проверку для сайта и сохранить результат в БД.
     */
    public function runTest(Site $site, string $testType): ?TestResult
    {
        $test = $this->registry->get($testType);

        if (! $test) {
            return null;
        }

        // Получаем предыдущий результат для сравнения статуса
        $previousResult = TestResult::latestForSiteTest($site->id, $testType)->first();
        $resultData = $test->run($site);
        $value = $resultData->value;
        $shouldDispatch = ! $previousResult || $previousResult->status !== $resultData->status;

        if ($testType === 'availability') {
            $previous = TestResult::latestForSiteTest($site->id, $testType)->limit(5)->get();
            $previousFailures = $previous
                ->map(fn (TestResult $result): bool => $this->availabilityIncidents->isProbeFailed($result))
                ->values()
                ->all();
            $currentFailed = $this->availabilityIncidents->isProbeFailedFromValue($value, $resultData->status);
            $summary = $this->availabilityIncidents->summarize(array_merge([$currentFailed], $previousFailures));
            $previousSummary = $this->availabilityIncidents->summarize($previousFailures);
            $value = array_merge(is_array($value) ? $value : [], $summary);
            $shouldDispatch = $previousSummary['incident_down'] !== $summary['incident_down'];
        }

        $result = TestResult::create([
            'site_id' => $site->id,
            'test_type' => $testType,
            'status' => $resultData->status,
            'value' => $value,
            'message' => $resultData->message,
            'checked_at' => now(),
        ]);

        Log::info("Test result: site={$site->id} ({$site->name}), test={$testType}, status={$result->status}, message={$result->message}");

        if ($shouldDispatch) {
            $this->events->dispatch(new TestStatusChanged($site, $result, $previousResult));
        }

        return $result;
    }

    /**
     * Проверить, нужно ли запускать тест для сайта
     */
    public function shouldRunTest(Site $site, string $testType, ?SiteTest $siteTest = null): bool
    {
        if (! $siteTest) {
            $siteTest = $site->getTestConfig($testType);
        }

        if (! $siteTest || ! $siteTest->is_enabled) {
            return false;
        }

        $lastResult = TestResult::latestForSiteTest($site->id, $testType)->first();

        if (! $lastResult) {
            return true;
        }

        $intervalMinutes = $siteTest->getIntervalMinutes();
        $nextCheckAt = $lastResult->checked_at->copy()->addMinutes($intervalMinutes);

        return now()->greaterThanOrEqualTo($nextCheckAt);
    }

    /**
     * Запустить все необходимые проверки для всех активных сайтов
     */
    public function runScheduledTests(): Collection
    {
        $results = collect();

        $sites = Site::where('is_active', true)->with('siteTests')->get();

        foreach ($sites as $site) {
            $this->initializeTestsForSite($site);
            $site->refresh();
            $site->load('siteTests');

            foreach ($this->registry->all() as $testType => $test) {
                $testConfig = $site->siteTests->firstWhere('test_type', $testType);

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
     * Инициализировать тесты для сайта
     */
    public function initializeTestsForSite(Site $site): void
    {
        foreach ($this->registry->all() as $testType => $test) {
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
}
