<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read Project|null $project
 */

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
        'project_id',
        'is_active',
        'telegram_chat_id',
        'notification_settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notification_settings' => 'array',
    ];

    /**
     * Получить пользователя, которому принадлежит сайт
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Получить проект, к которому привязан сайт.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Получить привязанный Telegram-чат
     */
    public function telegramChat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class);
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
     * Получить страницы из аудита карты сайта (текущее состояние)
     */
    public function auditPages(): HasMany
    {
        return $this->hasMany(AuditPage::class);
    }

    /**
     * Получить последний результат теста (любого типа) для сайта.
     * Использует latestOfMany для корректного LIMIT 1 на сайт (не глобальный!).
     */
    public function latestTestResult(): HasOne
    {
        return $this->hasOne(TestResult::class)->latestOfMany('checked_at');
    }

    /**
     * Получить результаты конкретного типа теста
     */
    public function testResultsByType(string $testType): HasMany
    {
        return $this->hasMany(TestResult::class)->where('test_type', $testType);
    }

    /**
     * Получить настройку конкретного теста.
     * Использует relationship siteTests() — работает с eager-loaded данными.
     */
    public function getTestConfig(string $testType): ?SiteTest
    {
        return $this->siteTests->firstWhere('test_type', $testType);
    }

    /**
     * Проверить, включен ли тест
     */
    public function isTestEnabled(string $testType): bool
    {
        $test = $this->getTestConfig($testType);

        return $test && $test->is_enabled;
    }

    /**
     * Проверить, включены ли Telegram-алерты
     */
    public function isTelegramAlertsEnabled(): bool
    {
        return $this->telegram_chat_id
            && ($this->notification_settings['alerts_enabled'] ?? false);
    }

    /**
     * Проверить, включено ли ежесуточное саммари
     */
    public function isTelegramSummaryEnabled(): bool
    {
        return $this->telegram_chat_id
            && ($this->notification_settings['summary_enabled'] ?? false);
    }
}
