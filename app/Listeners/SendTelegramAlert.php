<?php

namespace App\Listeners;

use App\Events\TestStatusChanged;
use App\Services\Telegram\TelegramBotInterface;
use App\Services\Telegram\TelegramMessageFormatter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель: отправка Telegram-алерта при смене статуса теста (SRP).
 * Выполняется асинхронно через очередь, чтобы не блокировать TestService.
 */
class SendTelegramAlert implements ShouldQueue
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
        protected readonly TelegramBotInterface $bot,
        protected readonly TelegramMessageFormatter $formatter,
    ) {}

    public function handle(TestStatusChanged $event): void
    {
        $site = $event->site;
        $site->loadMissing('telegramChat');

        if (! $site->isTelegramAlertsEnabled()) {
            return;
        }

        $chat = $site->telegramChat;
        if (! $chat) {
            return;
        }

        try {
            $message = $this->formatter->formatAlert(
                $site,
                $event->currentResult,
                $event->previousResult,
            );

            $this->bot->sendMessage($chat->chat_id, $message);
        } catch (\Exception $e) {
            Log::error("Failed to send Telegram alert for site {$site->id}: ".$e->getMessage());
        }
    }
}
