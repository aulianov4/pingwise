<?php

namespace App\Listeners;

use App\Events\ServerStatusChanged;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Алерт при смене статуса сервера.
 * Первый пинг без предыдущего heartbeat не уведомляет.
 */
class SendServerAlert implements ShouldQueue
{
    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        protected readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(ServerStatusChanged $event): void
    {
        if ($event->previousHeartbeat === null) {
            return;
        }

        try {
            $this->dispatcher->sendServerAlert(
                $event->server,
                $event->currentHeartbeat,
                $event->previousHeartbeat,
            );
        } catch (\Exception $e) {
            Log::error("Failed to send server alert for server {$event->server->id}: ".$e->getMessage());
        }
    }
}
