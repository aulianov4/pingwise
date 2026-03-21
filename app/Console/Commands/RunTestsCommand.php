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

        // Валидация: --test без --site не имеет смысла
        if ($testType && ! $siteId) {
            $this->error('Опция --test требует указания --site');

            return Command::FAILURE;
        }

        if ($siteId) {
            $site = \App\Models\Site::find($siteId);

            if (! $site) {
                $this->error("Сайт с ID {$siteId} не найден");

                return Command::FAILURE;
            }

            if ($testType) {
                // Запуск конкретного теста для конкретного сайта
                $this->info("Запуск теста {$testType} для сайта: {$site->name} ({$site->url})");
                $result = $testService->runTest($site, $testType);

                if ($result) {
                    $this->outputResult($result);

                    return Command::SUCCESS;
                } else {
                    $this->error("Тест {$testType} не найден");

                    return Command::FAILURE;
                }
            }

            // Запуск всех включённых тестов для конкретного сайта
            $this->info("Запуск всех тестов для сайта: {$site->name} ({$site->url})");
            $site->load('siteTests');

            if ($site->siteTests->isEmpty()) {
                $testService->initializeTestsForSite($site);
                $site->refresh();
                $site->load('siteTests');
            }

            $results = collect();
            foreach ($testService->getAllTests() as $type => $test) {
                if ($site->isTestEnabled($type)) {
                    $result = $testService->runTest($site, $type);
                    if ($result) {
                        $results->push($result);
                    }
                }
            }

            $this->outputResultsTable($results);

            return Command::SUCCESS;
        }

        // Запуск всех запланированных проверок
        $this->info('Запуск запланированных проверок...');

        $results = $testService->runScheduledTests();

        $this->outputResultsTable($results);

        return Command::SUCCESS;
    }

    /**
     * Вывести результат одного теста.
     */
    protected function outputResult(\App\Models\TestResult $result): void
    {
        $statusColor = match ($result->status) {
            'success' => 'green',
            'failed' => 'red',
            'warning' => 'yellow',
            default => 'white',
        };

        $this->line("Статус: <fg={$statusColor}>{$result->status}</>");
        $this->line("Сообщение: {$result->message}");

        if ($result->value) {
            $this->line('Детали: '.json_encode($result->value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }

    /**
     * Вывести таблицу результатов.
     */
    protected function outputResultsTable(\Illuminate\Support\Collection $results): void
    {
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
    }
}
