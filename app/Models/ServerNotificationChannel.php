<?php

namespace App\Models;

use Database\Factories\ServerNotificationChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Привязка сервера к каналу уведомлений (SRP).
 */
class ServerNotificationChannel extends Model
{
    /** @use HasFactory<ServerNotificationChannelFactory> */
    use HasFactory;

    protected $table = 'server_notification_channel';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_id',
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
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo<NotificationChannel, $this>
     */
    public function notificationChannel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class);
    }
}
