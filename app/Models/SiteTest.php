<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'test_type',
        'is_enabled',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Получить сайт
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Получить интервал проверки в минутах.
     *
     * Интервал берётся из settings (заполняется при инициализации через TestService).
     * Дефолтные значения по типам тестов больше не дублируются здесь —
     * они определены в самих тестах через getDefaultInterval() (OCP).
     */
    public function getIntervalMinutes(): int
    {
        if (isset($this->settings['interval_minutes'])) {
            return (int) $this->settings['interval_minutes'];
        }

        // Безопасный fallback — не зависит от конкретных типов тестов
        return 60;
    }
}
