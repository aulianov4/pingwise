<?php

namespace App\Observers;

use App\Models\Site;
use App\Services\TestService;
use Illuminate\Support\Facades\Log;

/**
 * Observer для модели Site (SRP).
 * Логика, ранее находившаяся в Site::boot() с вызовом app(),
 * вынесена сюда. Зависимости инжектируются явно (DIP).
 */
class SiteObserver
{
    public function __construct(
        protected readonly TestService $testService,
    ) {}

    public function created(Site $site): void
    {
        Log::info("Site created: {$site->id} ({$site->name})");

        try {
            $this->testService->initializeTestsForSite($site);
        } catch (\Exception $e) {
            Log::error("Failed to initialize tests for site {$site->id}: ".$e->getMessage());
        }
    }

    public function updated(Site $site): void
    {
        Log::info("Site updated: {$site->id} ({$site->name})");
    }

    public function deleted(Site $site): void
    {
        Log::info("Site deleted: {$site->id} ({$site->name})");
    }
}
