<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannelType;
use App\Enums\SummaryPeriod;
use App\Models\NotificationChannel;
use App\Models\Server;
use App\Models\ServerHeartbeat;
use App\Models\ServerNotificationChannel;
use App\Models\Site;
use App\Models\SiteTestNotificationChannel;
use App\Models\TestResult;
use App\Services\Servers\ServerSummaryBuilder;
use App\Services\Telegram\TelegramMessageFormatter;
use Illuminate\Support\Collection;

/**
 * Оркестрация отправки уведомлений через каналы (SRP).
 * Не знает про Telegram — только про драйверы и форматтер (DIP).
 */
class NotificationDispatcher
{
    public function __construct(
        protected readonly ChannelDriverRegistry $drivers,
        protected readonly TelegramMessageFormatter $formatter,
        protected readonly ServerSummaryBuilder $serverSummaries,
    ) {}

    /**
     * Отправить алерт о смене статуса по каналам теста.
     */
    public function sendAlert(Site $site, TestResult $current, TestResult $previous): void
    {
        $siteTest = $site->getTestConfig($current->test_type);

        if ($siteTest === null) {
            return;
        }

        $message = $this->formatter->formatAlert($site, $current, $previous);

        $assignments = $siteTest->notificationChannelAssignments()
            ->where('alerts', true)
            ->with('notificationChannel')
            ->get();

        foreach ($assignments as $assignment) {
            $this->sendToAssignment($assignment, $message);
        }
    }

    /**
     * Отправить алерт о смене статуса сервера.
     */
    public function sendServerAlert(Server $server, ServerHeartbeat $current, ServerHeartbeat $previous): void
    {
        $message = $this->formatter->formatServerAlert($server, $current, $previous);

        $assignments = $server->notificationChannelAssignments()
            ->where('alerts', true)
            ->with('notificationChannel')
            ->get();

        foreach ($assignments as $assignment) {
            $this->sendToServerAssignment($assignment, $message);
        }
    }

    /**
     * Отправить саммари периода: сайты и серверы отдельными сообщениями.
     *
     * @return int Количество успешно отправленных сообщений
     */
    public function sendSummaries(SummaryPeriod $period, ?string $atTime = null): int
    {
        return $this->sendSiteSummaries($period, $atTime) + $this->sendServerSummaries($period, $atTime);
    }

    /**
     * @return int Количество успешно отправленных сообщений
     */
    protected function sendSiteSummaries(SummaryPeriod $period, ?string $atTime = null): int
    {
        $flag = $period->pivotFlag();

        $assignments = SiteTestNotificationChannel::query()
            ->where($flag, true)
            ->with([
                'notificationChannel.project',
                'siteTest.site',
            ])
            ->get()
            ->filter(fn (SiteTestNotificationChannel $assignment): bool => $this->shouldSendSummary($assignment->notificationChannel, $assignment->siteTest?->site !== null, $atTime));

        $sent = 0;

        foreach ($assignments->groupBy('notification_channel_id') as $channelAssignments) {
            /** @var Collection<int, SiteTestNotificationChannel> $channelAssignments */
            $channel = $channelAssignments->first()?->notificationChannel;

            if ($channel === null) {
                continue;
            }

            $items = $this->buildSummaryItems($channelAssignments, $period);
            $message = $this->formatter->formatChannelSummary($channel, $period, $items);

            if ($this->sendToChannel($channel, $message)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @return int Количество успешно отправленных сообщений
     */
    protected function sendServerSummaries(SummaryPeriod $period, ?string $atTime = null): int
    {
        $flag = $period->pivotFlag();

        $assignments = ServerNotificationChannel::query()
            ->where($flag, true)
            ->with([
                'notificationChannel.project',
                'server.latestHeartbeat',
            ])
            ->get()
            ->filter(fn (ServerNotificationChannel $assignment): bool => $this->shouldSendSummary($assignment->notificationChannel, $assignment->server !== null, $atTime));

        $sent = 0;

        foreach ($assignments->groupBy('notification_channel_id') as $channelAssignments) {
            /** @var Collection<int, ServerNotificationChannel> $channelAssignments */
            $channel = $channelAssignments->first()?->notificationChannel;

            if ($channel === null) {
                continue;
            }

            $items = $this->serverSummaries->items($channelAssignments, $period);
            $message = $this->formatter->formatServerChannelSummary($channel, $period, $items);

            if ($this->sendToChannel($channel, $message)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param  Collection<int, SiteTestNotificationChannel>  $assignments
     * @return list<array{site: Site, test_type: string, last_result: ?TestResult, success: int, failed: int, warning: int, total: int}>
     */
    protected function buildSummaryItems(Collection $assignments, SummaryPeriod $period): array
    {
        $since = match ($period) {
            SummaryPeriod::Daily => now()->subDay(),
            SummaryPeriod::Weekly => now()->subWeek(),
            SummaryPeriod::Monthly => now()->subMonth(),
        };

        $items = [];

        foreach ($assignments as $assignment) {
            $siteTest = $assignment->siteTest;
            $site = $siteTest?->site;

            if ($siteTest === null || $site === null) {
                continue;
            }

            $results = TestResult::query()
                ->where('site_id', $site->id)
                ->where('test_type', $siteTest->test_type)
                ->where('checked_at', '>=', $since)
                ->orderByDesc('checked_at')
                ->get();

            $items[] = [
                'site' => $site,
                'test_type' => $siteTest->test_type,
                'last_result' => $results->first(),
                'success' => $results->where('status', 'success')->count(),
                'failed' => $results->where('status', 'failed')->count(),
                'warning' => $results->where('status', 'warning')->count(),
                'total' => $results->count(),
            ];
        }

        return $items;
    }

    protected function shouldSendSummary(?NotificationChannel $channel, bool $hasTarget, ?string $atTime): bool
    {
        if ($channel === null || ! $hasTarget || ! $channel->is_enabled || ! $channel->isConnected()) {
            return false;
        }

        if ($atTime === null) {
            return true;
        }

        return $channel->summaryTime() === $atTime;
    }

    protected function sendToAssignment(SiteTestNotificationChannel $assignment, string $message): void
    {
        $channel = $assignment->notificationChannel;

        if ($channel === null) {
            return;
        }

        $this->sendToChannel($channel, $message);
    }

    protected function sendToServerAssignment(ServerNotificationChannel $assignment, string $message): void
    {
        $channel = $assignment->notificationChannel;

        if ($channel === null) {
            return;
        }

        $this->sendToChannel($channel, $message);
    }

    protected function sendToChannel(NotificationChannel $channel, string $message): bool
    {
        $type = $channel->type instanceof NotificationChannelType
            ? $channel->type->value
            : (string) $channel->type;

        $driver = $this->drivers->get($type);

        if ($driver === null) {
            return false;
        }

        return $driver->send($channel, $message);
    }
}
