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
            $isEnabled = $siteTest && $siteTest->is_enabled;

            $lastResult = TestResult::where('site_id', $site->id)
                ->where('test_type', $testType)
                ->latest('checked_at')
                ->first();

            $results[] = [
                'type' => $testType,
                'name' => $test->getName(),
                'is_enabled' => $isEnabled,
                'last_result' => $lastResult,
                'status' => $lastResult?->status,
                'message' => $lastResult?->message,
                'value' => $lastResult?->value,
                'checked_at' => $lastResult?->checked_at,
            ];
        }

        return ['results' => $results];
    }
}


