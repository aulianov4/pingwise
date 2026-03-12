<?php

namespace App\Console\Commands;

use App\Models\TestResult;
use Illuminate\Console\Command;

class CleanupOldResultsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pingwise:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Удалить результаты тестов старше года';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoffDate = now()->subYear();
        
        $this->info("Удаление результатов тестов старше {$cutoffDate->format('Y-m-d H:i:s')}...");
        
        $deleted = TestResult::where('checked_at', '<', $cutoffDate)->delete();
        
        $this->info("Удалено записей: {$deleted}");
        
        return Command::SUCCESS;
    }
}
