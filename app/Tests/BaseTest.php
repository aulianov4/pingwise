<?php

namespace App\Tests;

use App\Models\Site;
use App\Models\TestResult;
use Illuminate\Support\Facades\Log;

abstract class BaseTest implements TestInterface
{
    /**
     * Запустить тест для сайта
     */
    public function run(Site $site): TestResult
    {
        try {
            $result = $this->execute($site);
            
            return TestResult::create([
                'site_id' => $site->id,
                'test_type' => $this->getType(),
                'status' => $result['status'],
                'value' => $result['value'] ?? null,
                'message' => $result['message'] ?? null,
                'checked_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Test {$this->getType()} failed for site {$site->id}: " . $e->getMessage());
            
            return TestResult::create([
                'site_id' => $site->id,
                'test_type' => $this->getType(),
                'status' => 'failed',
                'value' => null,
                'message' => 'Ошибка выполнения теста: ' . $e->getMessage(),
                'checked_at' => now(),
            ]);
        }
    }

    /**
     * Выполнить конкретную логику теста
     * 
     * @return array{status: string, value?: array, message?: string}
     */
    abstract protected function execute(Site $site): array;

    /**
     * Определить статус на основе результата
     */
    protected function determineStatus(bool $isSuccess, ?bool $isWarning = null): string
    {
        if (!$isSuccess) {
            return 'failed';
        }
        
        if ($isWarning === true) {
            return 'warning';
        }
        
        return 'success';
    }
}
