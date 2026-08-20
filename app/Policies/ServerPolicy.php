<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Server;
use App\Models\User;

/**
 * Политика доступа к серверам.
 * Суперадмин — полный доступ.
 * Администратор проекта — CRUD.
 * Наблюдатель — только просмотр.
 */
class ServerPolicy
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
        return true;
    }

    public function view(User $user, Server $server): bool
    {
        if ($server->project_id === null) {
            return false;
        }

        return $user->isMemberOf($server->project_id);
    }

    public function create(User $user): bool
    {
        return $user->projects()
            ->wherePivot('role', ProjectRole::Admin->value)
            ->exists();
    }

    public function update(User $user, Server $server): bool
    {
        if ($server->project_id === null) {
            return false;
        }

        return $user->isAdminOf($server->project_id);
    }

    public function delete(User $user, Server $server): bool
    {
        if ($server->project_id === null) {
            return false;
        }

        return $user->isAdminOf($server->project_id);
    }
}
