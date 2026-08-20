<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Привязка теста сайта к каналу уведомлений (SRP).
 */
class SiteTestNotificationChannel extends Model
{
    use HasFactory;

    protected $table = 'site_test_notification_channel';

    protected $fillable = [
        'site_test_id',
        'notification_channel_id',
        'alerts',
        'daily_summary',
        'weekly_summary',
        'monthly_summary',
    ];

    protected $casts = [
        'alerts' => 'boolean',
        'daily_summary' => 'boolean',
        'weekly_summary' => 'boolean',
        'monthly_summary' => 'boolean',
    ];

    /**
     * Настройка теста сайта.
     */
    public function siteTest(): BelongsTo
    {
        return $this->belongsTo(SiteTest::class);
    }

    /**
     * Канал уведомлений.
     */
    public function notificationChannel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class);
    }
}
