<?php

namespace App\Console\Commands;

use App\Models\ServerHeartbeat;
use App\Models\TestResult;
use Illuminate\Console\Command;

class CleanupOldResultsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pingwise:cleanup {--days=365 : Количество дней хранения результатов}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Удалить результаты тестов и heartbeat серверов старше указанного периода';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Удаление результатов тестов старше {$cutoffDate->format('Y-m-d H:i:s')}...");

        $deleted = TestResult::where('checked_at', '<', $cutoffDate)->delete();
        $deletedHeartbeats = ServerHeartbeat::where('reported_at', '<', $cutoffDate)->delete();

        $this->info("Удалено записей: {$deleted}");
        $this->info("Удалено heartbeat серверов: {$deletedHeartbeats}");

        return Command::SUCCESS;
    }
}
