<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель проекта.
 * Проект объединяет сайты и пользователей с ролями (SRP, DIP).
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'max_sites',
    ];

    protected $casts = [
        'max_sites' => 'integer',
    ];

    /**
     * Получить сайты проекта.
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Получить пользователей проекта с ролями.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps()
            ->using(ProjectUser::class);
    }

    /**
     * Получить администраторов проекта.
     */
    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', ProjectRole::Admin->value);
    }

    /**
     * Получить наблюдателей проекта.
     */
    public function observers(): BelongsToMany
    {
        return $this->users()->wherePivot('role', ProjectRole::Observer->value);
    }

    /**
     * Проверить, достигнут ли лимит сайтов в проекте.
     */
    public function hasReachedSiteLimit(): bool
    {
        return $this->sites()->count() >= $this->max_sites;
    }
}
