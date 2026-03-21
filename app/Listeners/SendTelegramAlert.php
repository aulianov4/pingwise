<?php

namespace App\Listeners;

use App\Events\TestStatusChanged;
use App\Services\Telegram\TelegramBotInterface;
use App\Services\Telegram\TelegramMessageFormatter;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель: отправка Telegram-алерта при смене статуса теста (SRP).
 */
class SendTelegramAlert
{
    public function __construct(
        protected readonly TelegramBotInterface $bot,
        protected readonly TelegramMessageFormatter $formatter,
    ) {}

    public function handle(TestStatusChanged $event): void
    {
        $site = $event->site;

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
