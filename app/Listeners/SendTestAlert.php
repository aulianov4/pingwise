<?php

namespace App\Listeners;

use App\Events\TestStatusChanged;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель: отправка алерта при смене статуса теста (SRP).
 * Первый прогон без предыдущего результата не уведомляет.
 */
class SendTestAlert implements ShouldQueue
{
    /**
     * Количество попыток выполнения задачи.
     */
    public int $tries = 3;

    /**
     * Задержка между повторными попытками (секунды).
     *
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        protected readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(TestStatusChanged $event): void
    {
        if ($event->previousResult === null) {
            return;
        }

        $site = $event->site;
        $site->loadMissing('siteTests');

        try {
            $this->dispatcher->sendAlert($site, $event->currentResult, $event->previousResult);
        } catch (\Exception $e) {
            Log::error("Failed to send test alert for site {$site->id}: ".$e->getMessage());
        }
    }
}
