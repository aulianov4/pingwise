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
     * Получить интервал проверки в минутах
     */
    public function getIntervalMinutes(): int
    {
        if (isset($this->settings['interval_minutes'])) {
            return (int) $this->settings['interval_minutes'];
        }
        
        // Интервал по умолчанию для типа теста
        return match ($this->test_type) {
            'availability' => 5,
            'ssl', 'domain' => 24 * 60, // 24 часа
            default => 60,
        };
    }
}
