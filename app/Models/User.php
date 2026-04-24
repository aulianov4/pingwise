<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /**
     * Определяет, может ли пользователь получить доступ к панели Filament.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Получить сайты пользователя (обратная совместимость, creator).
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Получить проекты пользователя с ролями.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot('role')
            ->withTimestamps()
            ->using(ProjectUser::class);
    }

    /**
     * Проверить, является ли пользователь суперадминистратором.
     */
    public function isSuperadmin(): bool
    {
        return $this->role === Role::Superadmin;
    }

    /**
     * Проверить роль пользователя в конкретном проекте.
     */
    public function projectRole(int $projectId): ?ProjectRole
    {
        $pivot = $this->projects()->wherePivot('project_id', $projectId)->first()?->pivot;

        return $pivot?->role;
    }

    /**
     * Проверить, является ли пользователь администратором проекта.
     */
    public function isAdminOf(int $projectId): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->projectRole($projectId) === ProjectRole::Admin;
    }

    /**
     * Проверить, имеет ли пользователь доступ к проекту (любая роль).
     */
    public function isMemberOf(int $projectId): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->projects()->wherePivot('project_id', $projectId)->exists();
    }

    /**
     * Получить ID проектов, к которым у пользователя есть доступ.
     *
     * @return list<int>
     */
    public function accessibleProjectIds(): array
    {
        if ($this->isSuperadmin()) {
            return Project::query()->pluck('id')->all();
        }

        return $this->projects()->pluck('projects.id')->all();
    }
}
