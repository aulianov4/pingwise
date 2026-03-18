<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'test_type',
        'status',
        'value',
        'message',
        'checked_at',
    ];

    protected $casts = [
        'value' => 'array',
        'checked_at' => 'datetime',
    ];

    /**
     * Получить сайт
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Scope для фильтрации по типу теста
     */
    public function scopeOfType($query, string $testType)
    {
        return $query->where('test_type', $testType);
    }

    /**
     * Scope для фильтрации по статусу
     */
    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope для получения последнего результата по сайту и типу теста
     */
    public function scopeLatestForSiteTest($query, int $siteId, string $testType)
    {
        return $query->where('site_id', $siteId)
            ->where('test_type', $testType)
            ->latest('checked_at');
    }

    /**
     * Scope для фильтрации за период
     */
    public function scopeForPeriod($query, string $period)
    {
        return match ($period) {
            'week' => $query->where('checked_at', '>=', now()->subWeek()),
            'month' => $query->where('checked_at', '>=', now()->subMonth()),
            'year' => $query->where('checked_at', '>=', now()->subYear()),
            default => $query,
        };
    }
}
