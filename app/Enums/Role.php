<?php

namespace App\Enums;

enum Role: string
{
    case Superadmin = 'superadmin';

    /**
     * Человекочитаемое название роли.
     */
    public function label(): string
    {
        return match ($this) {
            Role::Superadmin => 'Суперадминистратор',
        };
    }
}
