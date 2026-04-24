<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Страница сайта, обнаруженная в ходе аудита карты сайта (SRP).
 * Хранит текущее состояние URL — обновляется при каждом прогоне теста.
 * История агрегатов — в TestResult, история изменений — через last_in_sitemap_at и last_seen_at.
 */
class AuditPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'url',
        'status_code',
        'in_sitemap',
        'in_crawl',
        'redirect_target',
        'canonical',
        'first_seen_at',
        'last_seen_at',
        'last_in_sitemap_at',
    ];

    protected $casts = [
        'in_sitemap' => 'boolean',
        'in_crawl' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_in_sitemap_at' => 'datetime',
    ];

    /**
     * Сайт, которому принадлежит страница.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Мёртвые страницы: в sitemap, но недоступны (статус 0).
     */
    public function scopeDead(Builder $query): Builder
    {
        return $query->where('in_sitemap', true)->where('status_code', 0);
    }

    /**
     * Страницы с HTTP-ошибками в sitemap (не 0 и не 200, не редиректы).
     */
    public function scopeNon200(Builder $query): Builder
    {
        return $query
            ->where('in_sitemap', true)
            ->where('status_code', '!=', 0)
            ->where('status_code', '!=', 200)
            ->where(function (Builder $q): void {
                $q->where('status_code', '<', 300)->orWhere('status_code', '>=', 400);
            });
    }

    /**
     * Страницы с редиректами в sitemap (3xx или redirect_target указывает на другой URL).
     */
    public function scopeRedirecting(Builder $query): Builder
    {
        return $query
            ->where('in_sitemap', true)
            ->where(function (Builder $q): void {
                $q->whereBetween('status_code', [300, 399])
                    ->orWhereNotNull('redirect_target');
            });
    }

    /**
     * Страницы, найденные краулером, но отсутствующие в sitemap.
     */
    public function scopeMissingFromSitemap(Builder $query): Builder
    {
        return $query->where('in_crawl', true)->where('in_sitemap', false);
    }

    /**
     * Страницы с несовпадающим canonical-тегом.
     */
    public function scopeWithCanonicalIssue(Builder $query): Builder
    {
        return $query->whereNotNull('canonical')->whereColumn('canonical', '!=', 'url');
    }
}
