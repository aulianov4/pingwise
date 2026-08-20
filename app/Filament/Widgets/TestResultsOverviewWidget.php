<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use App\Models\TestResult;
use App\Services\TestService;
use Filament\Widgets\Widget;

class TestResultsOverviewWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.test-results-overview';

    public Site|int|null $record = null;

    protected function getViewData(): array
    {
        $site = $this->record;

        if (! $site instanceof Site) {
            return ['results' => []];
        }

        $testService = app(TestService::class);
        $allTests = $testService->getAllTests();

        $results = [];

        foreach ($allTests as $testType => $test) {
            $siteTest = $site->getTestConfig($testType);

            $lastResult = TestResult::latestForSiteTest($site->id, $testType)->first();
            $status = $lastResult?->status;

            if ($testType === 'availability' && $lastResult !== null) {
                $status = $lastResult->isAvailabilityIncidentDown() ? 'failed' : 'success';
            }

            $results[] = [
                'type' => $testType,
                'name' => $test->getName(),
                'is_enabled' => $siteTest?->is_enabled ?? false,
                'last_result' => $lastResult,
                'status' => $status,
                'message' => $lastResult?->message,
                'value' => $lastResult?->value,
                'ping_ms' => $testType === 'availability' ? $lastResult?->responseTimeMs() : null,
                'checked_at' => $lastResult?->checked_at,
            ];
        }

        return ['results' => $results];
    }
}
