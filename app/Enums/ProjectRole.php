<?php

namespace App\Enums;

enum ProjectRole: string
{
    case Admin = 'admin';
    case Observer = 'observer';

    /**
     * Человекочитаемое название роли в проекте.
     */
    public function label(): string
    {
        return match ($this) {
            ProjectRole::Admin => 'Администратор',
            ProjectRole::Observer => 'Наблюдатель',
        };
    }

    /**
     * Может ли роль редактировать сайты.
     */
    public function canEdit(): bool
    {
        return $this === ProjectRole::Admin;
    }
}
