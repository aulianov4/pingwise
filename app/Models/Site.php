<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель сайта (SRP).
 * Логика событий (created/updated/deleted) вынесена в SiteObserver.
 * Модель не обращается к сервисному контейнеру напрямую (DIP).
 */
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
