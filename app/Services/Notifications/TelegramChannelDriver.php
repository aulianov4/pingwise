<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Services\Telegram\TelegramBotInterface;

/**
 * Драйвер отправки в Telegram (SRP).
 */
class TelegramChannelDriver implements NotificationChannelDriver
{
    public function __construct(
        protected readonly TelegramBotInterface $bot,
    ) {}

    public function type(): string
    {
        return NotificationChannelType::Telegram->value;
    }

    public function send(NotificationChannel $channel, string $message): bool
    {
        if (! $channel->is_enabled || ! $channel->isConnected() || $channel->telegram_chat_id === null) {
            return false;
        }

        return $this->bot->sendMessage($channel->telegram_chat_id, $message);
    }
}
