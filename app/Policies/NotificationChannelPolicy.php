<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\NotificationChannel;
use App\Models\User;

/**
 * Политика каналов уведомлений.
 * Суперадмин — полный доступ.
 * Админ проекта — CRUD своих каналов.
 * Наблюдатель — только просмотр.
 */
class NotificationChannelPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->projects()->exists();
    }

    public function view(User $user, NotificationChannel $channel): bool
    {
        return $user->isMemberOf($channel->project_id);
    }

    public function create(User $user): bool
    {
        return $user->projects()
            ->wherePivot('role', ProjectRole::Admin->value)
            ->exists();
    }

    public function update(User $user, NotificationChannel $channel): bool
    {
        return $user->isAdminOf($channel->project_id);
    }

    public function delete(User $user, NotificationChannel $channel): bool
    {
        return $user->isAdminOf($channel->project_id);
    }
}
