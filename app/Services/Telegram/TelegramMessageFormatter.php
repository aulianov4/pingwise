<?php

namespace App\Services\Telegram;

use App\Models\Site;
use App\Models\TestResult;
use App\Services\TestRegistry;
use Illuminate\Support\Collection;

/**
 * Форматирование Telegram-сообщений (SRP).
 * Ответственность: только формирование текста сообщений.
 */
class TelegramMessageFormatter
{
    public function __construct(
        protected readonly TestRegistry $registry,
    ) {}

    /**
     * Форматировать алерт при смене статуса теста
     */
    public function formatAlert(Site $site, TestResult $current, ?TestResult $previous): string
    {
        $emoji = match ($current->status) {
            'success' => '✅',
            'warning' => '⚠️',
            'failed' => '🔴',
            default => 'ℹ️',
        };

        $testName = $this->getTestName($current->test_type);
        $oldStatus = $previous ? $this->getStatusLabel($previous->status) : '—';
        $newStatus = $this->getStatusLabel($current->status);

        $lines = [
            "{$emoji} <b>{$site->name}</b>",
            '',
            "Тест: <b>{$testName}</b>",
            "Статус: {$oldStatus} → <b>{$newStatus}</b>",
        ];

        if ($current->message) {
            $lines[] = '';
            $lines[] = htmlspecialchars($current->message, ENT_QUOTES);
        }

        $lines[] = '';
        $lines[] = '🔗 '.htmlspecialchars($site->url, ENT_QUOTES);
        $lines[] = '🕐 '.$current->checked_at->format('d.m.Y H:i:s');

        return implode("\n", $lines);
    }

    /**
     * Форматировать ежесуточное саммари для сайта
     */
    public function formatDailySummary(Site $site, Collection $results): string
    {
        $total = $results->count();
        $success = $results->where('status', 'success')->count();
        $failed = $results->where('status', 'failed')->count();
        $warnings = $results->where('status', 'warning')->count();

        $uptimePercent = $total > 0 ? round(($success / $total) * 100, 1) : 0;

        $emoji = match (true) {
            $uptimePercent >= 99 => '🟢',
            $uptimePercent >= 95 => '🟡',
            default => '🔴',
        };

        $lines = [
            "📊 <b>Сводка за сутки: {$site->name}</b>",
            '',
            "{$emoji} Аптайм: <b>{$uptimePercent}%</b>",
            "Всего проверок: {$total}",
            "✅ Успешных: {$success}",
        ];

        if ($warnings > 0) {
            $lines[] = "⚠️ Предупреждений: {$warnings}";
        }
        if ($failed > 0) {
            $lines[] = "🔴 Ошибок: {$failed}";
        }

        // Группируем по типам тестов
        $byType = $results->groupBy('test_type');
        if ($byType->count() > 1) {
            $lines[] = '';
            $lines[] = '<b>По тестам:</b>';

            foreach ($byType as $testType => $typeResults) {
                $typeTotal = $typeResults->count();
                $typeSuccess = $typeResults->where('status', 'success')->count();
                $typePercent = $typeTotal > 0 ? round(($typeSuccess / $typeTotal) * 100, 1) : 0;
                $testName = $this->getTestName($testType);
                $typeEmoji = $typePercent >= 99 ? '✅' : ($typePercent >= 95 ? '⚠️' : '🔴');

                $lines[] = "  {$typeEmoji} {$testName}: {$typePercent}% ({$typeSuccess}/{$typeTotal})";
            }
        }

        // Последний статус каждого теста
        $lastByType = $results->groupBy('test_type')->map(fn ($items) => $items->sortByDesc('checked_at')->first());
        if ($lastByType->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '<b>Текущий статус:</b>';
            foreach ($lastByType as $testType => $lastResult) {
                $testName = $this->getTestName($testType);
                $statusEmoji = match ($lastResult->status) {
                    'success' => '✅',
                    'warning' => '⚠️',
                    'failed' => '🔴',
                    default => '❓',
                };
                $lines[] = "  {$statusEmoji} {$testName}: {$this->getStatusLabel($lastResult->status)}";
            }
        }

        $lines[] = '';
        $lines[] = '🔗 '.htmlspecialchars($site->url, ENT_QUOTES);

        return implode("\n", $lines);
    }

    protected function getTestName(string $testType): string
    {
        return $this->registry->get($testType)?->getName() ?? $testType;
    }

    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'success' => 'Успешно',
            'warning' => 'Предупреждение',
            'failed' => 'Ошибка',
            default => $status,
        };
    }
}
