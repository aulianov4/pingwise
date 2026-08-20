<?php

namespace App\Services\Telegram;

/**
 * Интерфейс Telegram-бота (DIP).
 * Клиенты зависят от абстракции, а не от конкретной реализации.
 */
interface TelegramBotInterface
{
    /**
     * Отправить сообщение в чат
     */
    public function sendMessage(int $chatId, string $text, string $parseMode = 'HTML'): bool;

    /**
     * Проверить, настроен ли бот (есть ли токен)
     */
    public function isConfigured(): bool;

    /**
     * Установить webhook для входящих обновлений.
     */
    public function setWebhook(string $url): bool;

    /**
     * Удалить webhook.
     */
    public function deleteWebhook(bool $dropPendingUpdates = true): bool;

    /**
     * Получить обновления через long poll.
     *
     * @return list<array<string, mixed>>
     */
    public function getUpdates(?int $offset = null, int $timeout = 0): array;
}
