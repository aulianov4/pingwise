<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot-модель связи пользователя с проектом.
 */
class ProjectUser extends Pivot
{
    protected $table = 'project_user';

    protected $casts = [
        'role' => ProjectRole::class,
    ];

    /**
     * Получить проект.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Получить пользователя.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
