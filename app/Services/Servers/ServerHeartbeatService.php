<?php

namespace App\Services\Servers;

use App\Events\ServerStatusChanged;
use App\Models\Server;
use App\Models\ServerHeartbeat;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;

/**
 * Приём heartbeat и фиксация тишины (SRP).
 */
class ServerHeartbeatService
{
    public function __construct(
        protected readonly HeartbeatEvaluator $evaluator,
        protected readonly Dispatcher $events,
    ) {}

    /**
     * Сохранить пинг агента, обновить last_seen и при смене статуса диспатчить событие.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ingest(Server $server, array $payload, ?Carbon $reportedAt = null): ServerHeartbeat
    {
        $reportedAt = ($reportedAt ?? now())->copy();
        $previousHeartbeat = $server->latestHeartbeat;
        $lastLive = $this->lastLiveHeartbeat($server);
        $evaluation = $this->evaluator->evaluate(
            $payload,
            $server->resolvedSettings(),
            is_array($lastLive?->value) ? $lastLive->value : null,
        );

        $heartbeat = ServerHeartbeat::query()->create([
            'server_id' => $server->id,
            'source' => ServerHeartbeat::SOURCE_HEARTBEAT,
            'status' => $evaluation->status,
            'value' => $payload,
            'message' => $evaluation->message,
            'reported_at' => $reportedAt,
        ]);

        $previousStatus = $server->last_status;
        $hostname = $payload['hostname'] ?? null;

        $server->forceFill([
            'hostname' => is_string($hostname) && $hostname !== '' ? $hostname : $server->hostname,
            'last_seen_at' => $reportedAt,
            'last_status' => $evaluation->status,
            'last_message' => $evaluation->message,
        ])->save();

        if ($this->shouldDispatch($previousHeartbeat, $previousStatus, $evaluation->status, ServerHeartbeat::SOURCE_HEARTBEAT)) {
            $this->events->dispatch(new ServerStatusChanged(
                $server->fresh() ?? $server,
                $heartbeat,
                $previousHeartbeat,
            ));
        }

        return $heartbeat;
    }

    /**
     * Если пингов нет дольше timeout — синтетический failed и алерт.
     */
    public function markSilentIfNeeded(Server $server, ?Carbon $now = null): ?ServerHeartbeat
    {
        $now ??= now();

        if (! $server->is_active || $server->last_seen_at === null) {
            return null;
        }

        $timeout = max(1, (int) $server->setting('silence_timeout_minutes', 30));

        if ($server->last_seen_at->gt($now->copy()->subMinutes($timeout))) {
            return null;
        }

        $previousHeartbeat = $server->latestHeartbeat;

        if ($previousHeartbeat?->source === ServerHeartbeat::SOURCE_SILENCE) {
            return null;
        }

        $minutes = (int) $server->last_seen_at->diffInMinutes($now);
        $evaluation = $this->evaluator->silence($minutes);
        $previousStatus = $server->last_status;

        $heartbeat = ServerHeartbeat::query()->create([
            'server_id' => $server->id,
            'source' => ServerHeartbeat::SOURCE_SILENCE,
            'status' => $evaluation->status,
            'value' => [
                'silence' => true,
                'minutes_without_ping' => $minutes,
            ],
            'message' => $evaluation->message,
            'reported_at' => $now,
        ]);

        $server->forceFill([
            'last_status' => $evaluation->status,
            'last_message' => $evaluation->message,
        ])->save();

        if ($this->shouldDispatch($previousHeartbeat, $previousStatus, $evaluation->status, ServerHeartbeat::SOURCE_SILENCE)) {
            $this->events->dispatch(new ServerStatusChanged(
                $server->fresh() ?? $server,
                $heartbeat,
                $previousHeartbeat,
            ));
        }

        return $heartbeat;
    }

    /**
     * Проверить все активные серверы на тишину.
     */
    public function checkSilence(?Carbon $now = null): int
    {
        $count = 0;

        Server::query()
            ->where('is_active', true)
            ->whereNotNull('last_seen_at')
            ->orderBy('id')
            ->each(function (Server $server) use ($now, &$count): void {
                $server->loadMissing('latestHeartbeat');

                if ($this->markSilentIfNeeded($server, $now) !== null) {
                    $count++;
                }
            });

        return $count;
    }

    protected function lastLiveHeartbeat(Server $server): ?ServerHeartbeat
    {
        return ServerHeartbeat::query()
            ->where('server_id', $server->id)
            ->where('source', ServerHeartbeat::SOURCE_HEARTBEAT)
            ->latest('reported_at')
            ->first();
    }

    protected function shouldDispatch(
        ?ServerHeartbeat $previousHeartbeat,
        ?string $previousStatus,
        string $currentStatus,
        string $currentSource,
    ): bool {
        if ($previousHeartbeat === null) {
            return false;
        }

        if ($currentSource === ServerHeartbeat::SOURCE_SILENCE) {
            return true;
        }

        return $previousStatus !== null && $previousStatus !== $currentStatus;
    }
}
