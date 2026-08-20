<?php

namespace App\Models;

use App\Enums\NotificationChannelType;
use Database\Factories\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Канал уведомлений проекта (SRP).
 * Хранит подключение (Telegram и др.) и токен привязки.
 *
 * @property array<string, mixed>|null $config
 */
class NotificationChannel extends Model
{
    /** @use HasFactory<NotificationChannelFactory> */
    use HasFactory;

    public const CONNECT_TOKEN_TTL_MINUTES = 30;

    public const DEFAULT_SUMMARY_TIME = '09:00';

    public const SUMMARY_TIMEZONE = 'Europe/Moscow';

    protected $fillable = [
        'project_id',
        'name',
        'type',
        'is_enabled',
        'summary_time',
        'telegram_chat_id',
        'connect_token',
        'connect_token_expires_at',
        'config',
    ];

    protected $casts = [
        'type' => NotificationChannelType::class,
        'is_enabled' => 'boolean',
        'telegram_chat_id' => 'integer',
        'connect_token_expires_at' => 'datetime',
        'config' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (self $channel): void {
            if ($channel->type === NotificationChannelType::Telegram
                && $channel->telegram_chat_id === null
                && blank($channel->connect_token)) {
                $channel->issueConnectToken();
            }
        });
    }

    /**
     * Проект, которому принадлежит канал.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Привязки тестов сайтов к этому каналу.
     */
    public function siteTestAssignments(): HasMany
    {
        return $this->hasMany(SiteTestNotificationChannel::class);
    }

    /**
     * Канал готов к отправке сообщений.
     */
    public function isConnected(): bool
    {
        return $this->type === NotificationChannelType::Telegram
            && $this->telegram_chat_id !== null;
    }

    /**
     * Выпущенный код ещё действителен.
     */
    public function hasActiveConnectToken(): bool
    {
        return filled($this->connect_token)
            && $this->connect_token_expires_at !== null
            && $this->connect_token_expires_at->isFuture();
    }

    /**
     * Выпустить новый код /connect.
     */
    public function issueConnectToken(): void
    {
        do {
            $token = 'PW-'.Str::upper(Str::random(4));
        } while (static::query()->where('connect_token', $token)->whereKeyNot($this->id)->exists());

        $this->connect_token = $token;
        $this->connect_token_expires_at = now()->addMinutes(self::CONNECT_TOKEN_TTL_MINUTES);
        $this->save();
    }

    /**
     * Привязать Telegram-чат к каналу.
     */
    public function connectTelegram(int $chatId, string $title, string $chatType): void
    {
        $config = $this->config ?? [];
        $config['chat_title'] = $title;
        $config['chat_type'] = $chatType;
        $config['connected_at'] = now()->toIso8601String();

        $this->forceFill([
            'telegram_chat_id' => $chatId,
            'connect_token' => null,
            'connect_token_expires_at' => null,
            'config' => $config,
        ])->save();
    }

    /**
     * Найти канал по коду /connect.
     */
    public static function findByConnectToken(string $token): ?self
    {
        $normalized = Str::upper(trim($token));

        if (! str_starts_with($normalized, 'PW-')) {
            $normalized = 'PW-'.$normalized;
        }

        return static::query()
            ->where('connect_token', $normalized)
            ->where('connect_token_expires_at', '>', now())
            ->first();
    }

    /**
     * Заголовок подключённого чата.
     */
    public function connectedChatTitle(): ?string
    {
        $title = $this->config['chat_title'] ?? null;

        return is_string($title) && $title !== '' ? $title : null;
    }

    /**
     * Scope: только включённые каналы.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Текущие дата и время по Москве (для саммари).
     */
    public static function summaryNow(?\DateTimeInterface $now = null): Carbon
    {
        $now = $now === null ? now() : Carbon::parse($now);

        return $now->copy()->timezone(self::SUMMARY_TIMEZONE);
    }

    /**
     * Время саммари в формате HH:MM (московское).
     */
    public function summaryTime(): string
    {
        $value = is_string($this->summary_time) && $this->summary_time !== ''
            ? $this->summary_time
            : self::DEFAULT_SUMMARY_TIME;

        return substr($value, 0, 5);
    }

    /**
     * Сейчас время отправки саммари для этого канала (по Москве).
     */
    public function isSummaryDue(?\DateTimeInterface $now = null): bool
    {
        return $this->summaryTime() === self::summaryNow($now)->format('H:i');
    }
}
