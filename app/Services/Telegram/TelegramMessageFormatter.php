<?php

namespace App\Services\Telegram;

use App\Enums\SummaryPeriod;
use App\Models\NotificationChannel;
use App\Models\Site;
use App\Models\TestResult;
use App\Services\TestRegistry;

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
     * Форматировать саммари канала за период.
     *
     * @param  list<array{site: Site, test_type: string, last_result: ?TestResult, success: int, failed: int, warning: int, total: int}>  $items
     */
    public function formatChannelSummary(NotificationChannel $channel, SummaryPeriod $period, array $items): string
    {
        $projectName = $channel->project?->name ?? 'Проект';
        $periodLabel = $period->label();

        $lines = [
            "📊 <b>Сводка за {$periodLabel}: {$projectName}</b>",
            "Канал: {$channel->name}",
        ];

        $bySite = collect($items)->groupBy(fn (array $item): int => $item['site']->id);

        foreach ($bySite as $siteItems) {
            /** @var Site $site */
            $site = $siteItems->first()['site'];
            $lines[] = '';
            $lines[] = '<b>'.htmlspecialchars($site->name, ENT_QUOTES).'</b>';
            $lines[] = htmlspecialchars($site->url, ENT_QUOTES);

            foreach ($siteItems as $item) {
                $testName = $this->getTestName($item['test_type']);
                $last = $item['last_result'];
                $statusEmoji = match ($last?->status) {
                    'success' => '✅',
                    'warning' => '⚠️',
                    'failed' => '🔴',
                    default => '❓',
                };
                $statusLabel = $last ? $this->getStatusLabel($last->status) : 'нет данных';
                $lines[] = "  {$statusEmoji} {$testName}: {$statusLabel} ({$item['success']}/{$item['total']})";
            }
        }

        if ($items === []) {
            $lines[] = '';
            $lines[] = 'Нет данных за период.';
        }

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
