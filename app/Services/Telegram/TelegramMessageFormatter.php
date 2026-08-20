<?php

namespace App\Services\Telegram;

use App\DTO\ServerSummaryItem;
use App\Enums\SummaryPeriod;
use App\Models\NotificationChannel;
use App\Models\Server;
use App\Models\ServerHeartbeat;
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

        if ($current->test_type === 'availability') {
            $down = $current->isAvailabilityIncidentDown();
            $emoji = $down ? '🔴' : '✅';
            $oldStatus = $previous === null
                ? '—'
                : ($previous->isAvailabilityIncidentDown() ? 'Недоступен' : 'Работает');
            $newStatus = $down ? 'Недоступен' : 'Работает';
        }

        $lines = [
            "{$emoji} <b>{$site->name}</b>",
            '',
            "Тест: <b>{$testName}</b>",
            "Статус: {$oldStatus} → <b>{$newStatus}</b>",
        ];

        if ($current->test_type === 'availability') {
            $failures = (int) ($current->value['window_failures'] ?? 0);
            $window = (int) ($current->value['window_size'] ?? 0);
            $ping = $current->responseTimeMs();
            $lines[] = "Пробы: {$failures} неудачных из {$window}";

            if ($ping !== null) {
                $lines[] = "Пинг последней проверки: {$ping} мс";
            }
        }

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
     * Форматировать алерт при смене статуса сервера.
     */
    public function formatServerAlert(Server $server, ServerHeartbeat $current, ?ServerHeartbeat $previous): string
    {
        $emoji = match ($current->status) {
            'success' => '✅',
            'warning' => '⚠️',
            'failed' => '🔴',
            default => 'ℹ️',
        };

        $oldStatus = $previous ? $this->getStatusLabel($previous->status) : '—';
        $newStatus = $this->getStatusLabel($current->status);
        $name = htmlspecialchars($server->name, ENT_QUOTES);

        $lines = [
            "{$emoji} <b>{$name}</b>",
            '',
        ];

        if (is_string($server->hostname) && $server->hostname !== '') {
            $lines[] = 'Хост: '.htmlspecialchars($server->hostname, ENT_QUOTES);
        }

        $lines[] = "Статус: {$oldStatus} → <b>{$newStatus}</b>";

        if ($current->message) {
            $lines[] = '';
            $lines[] = htmlspecialchars($current->message, ENT_QUOTES);
        }

        $lines[] = '';
        $lines[] = '🕐 '.$current->reported_at->format('d.m.Y H:i:s');

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

    /**
     * Форматировать саммари серверов канала за период.
     *
     * @param  list<ServerSummaryItem>  $items
     */
    public function formatServerChannelSummary(NotificationChannel $channel, SummaryPeriod $period, array $items): string
    {
        $projectName = $channel->project?->name ?? 'Проект';
        $title = match ($period) {
            SummaryPeriod::Daily => 'Серверы за сутки',
            SummaryPeriod::Weekly => 'Серверы за неделю',
            SummaryPeriod::Monthly => 'Серверы за месяц',
        };

        $lines = [
            "📊 <b>{$title}: ".htmlspecialchars($projectName, ENT_QUOTES).'</b>',
            'Канал: '.htmlspecialchars($channel->name, ENT_QUOTES),
        ];

        foreach ($items as $item) {
            $lines[] = '';
            $lines[] = $this->formatServerSummaryItem($item, $period);
        }

        if ($items === []) {
            $lines[] = '';
            $lines[] = 'Нет данных за период.';
        }

        return implode("\n", $lines);
    }

    protected function formatServerSummaryItem(ServerSummaryItem $item, SummaryPeriod $period): string
    {
        $server = $item->server;
        $name = htmlspecialchars($server->name, ENT_QUOTES);
        $hostname = is_string($server->hostname) && $server->hostname !== ''
            ? ' ('.htmlspecialchars($server->hostname, ENT_QUOTES).')'
            : '';

        if ($item->awaitingAgent) {
            return "❓ <b>{$name}</b>{$hostname}\nОжидает агент";
        }

        $emoji = match ($item->status) {
            'success' => '✅',
            'warning' => '⚠️',
            'failed' => '🔴',
            default => '❓',
        };

        $block = ["{$emoji} <b>{$name}</b>{$hostname}"];

        if ($item->currentlySilent) {
            $silent = $item->lastMessage ?: 'Нет пинга';
            $block[] = htmlspecialchars($silent, ENT_QUOTES);
        } else {
            $snapshot = $this->formatSnapshot($item);
            if ($snapshot !== null) {
                $block[] = $snapshot;
            }
        }

        if ($item->lastSeenAt !== null) {
            $block[] = 'Последний пинг: '.$item->lastSeenAt->format('d.m.Y H:i');
        }

        $block[] = 'За '.$period->label().': '.$this->formatPeriodStats($item);

        return implode("\n", $block);
    }

    protected function formatSnapshot(ServerSummaryItem $item): ?string
    {
        $parts = [];

        if ($item->ramAvailablePercent !== null) {
            $parts[] = 'RAM доступно '.$item->ramAvailablePercent.'%';
        }

        if ($item->diskUsedPercent !== null) {
            $parts[] = 'диск '.$item->diskUsedPercent.'%';
        }

        if ($item->uptimeSeconds !== null) {
            $parts[] = 'аптайм '.$this->formatUptime($item->uptimeSeconds);
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    protected function formatPeriodStats(ServerSummaryItem $item): string
    {
        $silence = 'тишина '.$item->silenceIncidents;

        if ($item->currentlySilent && $item->silentSince !== null) {
            $silence .= ' (с '.$item->silentSince->format('d.m H:i').')';
        }

        $warning = 'warning '.$item->warningCount;
        if ($item->warningCount > 0 && $item->warningReasons !== []) {
            $warning .= ' ('.implode(', ', $item->warningReasons).')';
        }

        $parts = [$silence, $warning];

        if ($item->failedCount > 0) {
            $failed = 'failed '.$item->failedCount;
            if ($item->failedReasons !== []) {
                $failed .= ' ('.implode(', ', $item->failedReasons).')';
            }
            $parts[] = $failed;
        }

        $parts[] = 'reboot '.$item->rebootCount;

        return implode(', ', $parts);
    }

    protected function formatUptime(int $seconds): string
    {
        if ($seconds < 3600) {
            return max(1, intdiv($seconds, 60)).' мин';
        }

        if ($seconds < 172800) {
            return max(1, intdiv($seconds, 3600)).' ч';
        }

        return max(1, intdiv($seconds, 86400)).' дн.';
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
