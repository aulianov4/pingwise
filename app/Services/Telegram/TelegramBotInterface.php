<?php

namespace App\Services\Telegram;

use App\Models\TelegramChat;
use Illuminate\Support\Collection;

/**
 * Интерфейс Telegram-бота (DIP).
 * Клиенты зависят от абстракции, а не от конкретной реализации.
 */
interface TelegramBotInterface
{
    /**
     * Синхронизировать список чатов бота из Telegram API
     *
     * @return Collection<int, TelegramChat>
     */
    public function syncChats(): Collection;

    /**
     * Отправить сообщение в чат
     */
    public function sendMessage(int $chatId, string $text, string $parseMode = 'HTML'): bool;

    /**
     * Проверить, настроен ли бот (есть ли токен)
     */
    public function isConfigured(): bool;
}

