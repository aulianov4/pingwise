<?php

namespace App\Services\Servers;

use App\DTO\ServerSummaryItem;
use App\Enums\SummaryPeriod;
use App\Models\Server;
use App\Models\ServerHeartbeat;
use App\Models\ServerNotificationChannel;
use Illuminate\Support\Collection;

/**
 * Сборка строк саммари по серверам за период (SRP).
 */
class ServerSummaryBuilder
{
    /**
     * @param  Collection<int, ServerNotificationChannel>  $assignments
     * @return list<ServerSummaryItem>
     */
    public function items(Collection $assignments, SummaryPeriod $period): array
    {
        $servers = $assignments
            ->map(fn (ServerNotificationChannel $assignment): ?Server => $assignment->server)
            ->filter(fn (?Server $server): bool => $server !== null)
            ->unique('id')
            ->values();

        if ($servers->isEmpty()) {
            return [];
        }

        $since = match ($period) {
            SummaryPeriod::Daily => now()->subDay(),
            SummaryPeriod::Weekly => now()->subWeek(),
            SummaryPeriod::Monthly => now()->subMonth(),
        };

        $serverIds = $servers->pluck('id')->all();

        $periodHeartbeats = ServerHeartbeat::query()
            ->whereIn('server_id', $serverIds)
            ->where('reported_at', '>=', $since)
            ->orderBy('reported_at')
            ->get()
            ->groupBy('server_id');

        $latestLive = $this->latestLiveHeartbeats($serverIds);

        $items = [];

        foreach ($servers as $server) {
            $heartbeats = $periodHeartbeats->get($server->id, collect());
            $live = $latestLive->get($server->id);
            $latest = $server->latestHeartbeat;
            $awaiting = $server->last_seen_at === null;
            $currentlySilent = ! $awaiting && ($latest?->isSilence() ?? false);

            $warningReasons = [];
            $failedReasons = [];
            $warningCount = 0;
            $failedCount = 0;

            foreach ($heartbeats as $heartbeat) {
                if (! $heartbeat instanceof ServerHeartbeat || $heartbeat->isSilence()) {
                    continue;
                }

                if ($this->isReboot($heartbeat)) {
                    continue;
                }

                $reason = $this->reasonFromMessage($heartbeat->message);

                if ($heartbeat->status === 'warning') {
                    $warningCount++;
                    if ($reason !== null) {
                        $warningReasons[] = $reason;
                    }
                }

                if ($heartbeat->status === 'failed') {
                    $failedCount++;
                    if ($reason !== null) {
                        $failedReasons[] = $reason;
                    }
                }
            }

            $silentMinutes = null;
            if ($currentlySilent && $server->last_seen_at !== null) {
                $silentMinutes = max(1, (int) $server->last_seen_at->diffInMinutes(now()));
            }

            $items[] = new ServerSummaryItem(
                server: $server,
                awaitingAgent: $awaiting,
                currentlySilent: $currentlySilent,
                status: $server->last_status,
                lastMessage: $server->last_message,
                lastSeenAt: $server->last_seen_at,
                silentSince: $currentlySilent ? $server->last_seen_at : null,
                silentMinutes: $silentMinutes,
                ramAvailablePercent: $live?->memoryAvailablePercent(),
                diskUsedPercent: $live?->worstDiskUsedPercent(),
                uptimeSeconds: $this->uptimeSeconds($live),
                silenceIncidents: $heartbeats->where('source', ServerHeartbeat::SOURCE_SILENCE)->count(),
                warningCount: $warningCount,
                failedCount: $failedCount,
                warningReasons: array_values(array_unique($warningReasons)),
                failedReasons: array_values(array_unique($failedReasons)),
                rebootCount: $this->rebootCount($heartbeats),
            );
        }

        return $items;
    }

    /**
     * @param  list<int>  $serverIds
     * @return Collection<int, ServerHeartbeat>
     */
    protected function latestLiveHeartbeats(array $serverIds): Collection
    {
        return ServerHeartbeat::query()
            ->whereIn('server_id', $serverIds)
            ->where('source', ServerHeartbeat::SOURCE_HEARTBEAT)
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->get()
            ->unique('server_id')
            ->keyBy('server_id');
    }

    /**
     * @param  Collection<int, ServerHeartbeat>  $heartbeats
     */
    protected function rebootCount(Collection $heartbeats): int
    {
        $previous = null;
        $count = 0;

        foreach ($heartbeats as $heartbeat) {
            if (! $heartbeat instanceof ServerHeartbeat || $heartbeat->isSilence()) {
                continue;
            }

            $uptime = $this->uptimeSeconds($heartbeat);

            if ($previous !== null && $uptime !== null && $uptime < $previous) {
                $count++;
            }

            if ($uptime !== null) {
                $previous = $uptime;
            }
        }

        return $count;
    }

    protected function uptimeSeconds(?ServerHeartbeat $heartbeat): ?int
    {
        $uptime = $heartbeat?->value['uptime_seconds'] ?? null;

        return is_numeric($uptime) ? (int) $uptime : null;
    }

    protected function isReboot(ServerHeartbeat $heartbeat): bool
    {
        return is_string($heartbeat->message)
            && str_contains($heartbeat->message, 'перезагрузился');
    }

    protected function reasonFromMessage(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        if (str_contains($message, 'Диск')) {
            return 'диск';
        }

        if (str_contains($message, 'Память')) {
            return 'память';
        }

        if (str_contains($message, 'Swap')) {
            return 'swap';
        }

        return null;
    }
}
