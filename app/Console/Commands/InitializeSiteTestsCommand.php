<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\TestService;
use Illuminate\Console\Command;

class InitializeSiteTestsCommand extends Command
{
    protected $signature = 'pingwise:init-tests {--site= : ID сайта (если не указан, для всех сайтов)}';
    protected $description = 'Инициализировать тесты для сайта(ов)';

    public function handle(TestService $testService): int
    {
        $siteId = $this->option('site');
        
        if ($siteId) {
            $site = Site::find($siteId);
            if (!$site) {
                $this->error("Сайт с ID {$siteId} не найден");
                return 1;
            }
            $sites = collect([$site]);
        } else {
            $sites = Site::all();
        }
        
        $this->info("Инициализация тестов для " . $sites->count() . " сайт(ов)...");
        
        foreach ($sites as $site) {
            $this->info("Инициализация тестов для сайта {$site->id} ({$site->name})...");
            
            $testService->initializeTestsForSite($site);
            
            $count = $site->siteTests()->count();
            $this->info("  Создано тестов: {$count}");
        }
        
        $this->info("Готово!");
        return 0;
    }
}
