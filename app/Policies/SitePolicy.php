<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Site;
use App\Models\User;

/**
 * Политика доступа к сайтам.
 * Суперадмин — полный доступ.
 * Администратор проекта — CRUD.
 * Наблюдатель — только просмотр.
 */
class SitePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Site $site): bool
    {
        if ($site->project_id === null) {
            return false;
        }

        return $user->isMemberOf($site->project_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Может создавать, если является администратором хотя бы одного проекта
        return $user->projects()
            ->wherePivot('role', ProjectRole::Admin->value)
            ->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Site $site): bool
    {
        if ($site->project_id === null) {
            return false;
        }

        return $user->isAdminOf($site->project_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Site $site): bool
    {
        if ($site->project_id === null) {
            return false;
        }

        return $user->isAdminOf($site->project_id);
    }
}
