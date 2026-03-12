<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::created(function (Site $site) {
            Log::info("Site created: {$site->id} ({$site->name})");
            
            // Инициализировать тесты для нового сайта
            try {
                $testService = app(\App\Services\TestService::class);
                $testService->initializeTestsForSite($site);
            } catch (\Exception $e) {
                Log::error("Failed to initialize tests for site {$site->id}: " . $e->getMessage());
            }
        });

        static::updated(function (Site $site) {
            Log::info("Site updated: {$site->id} ({$site->name})");
        });

        static::deleted(function (Site $site) {
            Log::info("Site deleted: {$site->id} ({$site->name})");
        });
    }

    /**
     * Получить пользователя, которому принадлежит сайт
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Получить настройки тестов для сайта
     */
    public function siteTests(): HasMany
    {
        return $this->hasMany(SiteTest::class);
    }

    /**
     * Получить результаты тестов
     */
    public function testResults(): HasMany
    {
        return $this->hasMany(TestResult::class);
    }

    /**
     * Получить результаты конкретного типа теста
     */
    public function testResultsByType(string $testType): HasMany
    {
        return $this->hasMany(TestResult::class)->where('test_type', $testType);
    }

    /**
     * Получить настройку конкретного теста
     */
    public function getTestConfig(string $testType): ?SiteTest
    {
        return SiteTest::where('site_id', $this->id)
            ->where('test_type', $testType)
            ->first();
    }

    /**
     * Проверить, включен ли тест
     */
    public function isTestEnabled(string $testType): bool
    {
        $test = $this->getTestConfig($testType);
        return $test && $test->is_enabled;
    }
}
