<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;

/**
 * Драйвер канала уведомлений (DIP, OCP).
 * Новые типы каналов добавляются реализацией этого интерфейса.
 */
interface NotificationChannelDriver
{
    /**
     * Тип канала, который обслуживает драйвер.
     */
    public function type(): string;

    /**
     * Отправить сообщение в канал.
     */
    public function send(NotificationChannel $channel, string $message): bool;
}
