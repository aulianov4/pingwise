<?php

namespace App\Enums;

enum NotificationChannelType: string
{
    case Telegram = 'telegram';

    /**
     * Человекочитаемое название типа канала.
     */
    public function label(): string
    {
        return match ($this) {
            NotificationChannelType::Telegram => 'Telegram',
        };
    }
}
