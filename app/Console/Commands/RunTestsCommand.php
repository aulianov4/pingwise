<?php

namespace App\Console\Commands;

use App\Services\TestService;
use Illuminate\Console\Command;

class RunTestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pingwise:check 
                            {--site= : ID сайта для проверки конкретного сайта}
                            {--test= : Тип теста для запуска конкретного теста}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Запустить проверки для сайтов';

    /**
     * Execute the console command.
     */
    public function handle(TestService $testService): int
    {
        $siteId = $this->option('site');
        $testType = $this->option('test');

        if ($siteId && $testType) {
            // Запуск конкретного теста для конкретного сайта
            $site = \App\Models\Site::find($siteId);
            
            if (!$site) {
                $this->error("Сайт с ID {$siteId} не найден");
                return Command::FAILURE;
            }

            $this->info("Запуск теста {$testType} для сайта: {$site->name}");
            $result = $testService->runTest($site, $testType);
            
            if ($result) {
                $this->info("Результат: {$result->status} - {$result->message}");
                return Command::SUCCESS;
            } else {
                $this->error("Тест {$testType} не найден");
                return Command::FAILURE;
            }
        }

        // Запуск всех запланированных проверок
        $this->info('Запуск запланированных проверок...');
        
        $results = $testService->runScheduledTests();
        
        $this->info("Выполнено проверок: {$results->count()}");
        
        if ($results->isNotEmpty()) {
            $this->table(
                ['Сайт', 'Тест', 'Статус', 'Сообщение'],
                $results->map(function ($result) {
                    return [
                        $result->site->name,
                        $result->test_type,
                        $result->status,
                        substr($result->message ?? '', 0, 50),
                    ];
                })->toArray()
            );
        }

        return Command::SUCCESS;
    }
}
