<?php

namespace App\Models;

use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Мониторируемый сервер (SRP).
 * Токен агента хранится только как hash; plaintext показывается один раз.
 */
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    public const TOKEN_PREFIX = 'pw_srv_';

    public const SOURCE_HEARTBEAT = 'heartbeat';

    public const SOURCE_SILENCE = 'silence';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'hostname',
        'token_hash',
        'token_prefix',
        'is_active',
        'last_seen_at',
        'last_status',
        'last_message',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(): array
    {
        return [
            'heartbeat_interval_minutes' => 10,
            'silence_timeout_minutes' => 30,
            'disk_warning_percent' => 85,
            'disk_critical_percent' => 95,
            'disk_warning_free_bytes' => 2 * 1024 * 1024 * 1024,
            'disk_critical_free_bytes' => 512 * 1024 * 1024,
            'inode_warning_percent' => 80,
            'inode_critical_percent' => 95,
            'memory_warning_percent' => 10,
            'memory_critical_percent' => 3,
            'memory_warning_available_bytes' => 256 * 1024 * 1024,
            'memory_critical_available_bytes' => 64 * 1024 * 1024,
            'swap_warning_percent' => 50,
            'swap_critical_percent' => 90,
            'load_alert_enabled' => false,
            'load_warning_per_core' => 2.0,
            'monitored_mounts' => ['/'],
        ];
    }

    /**
     * Сгенерировать plaintext-токен агента.
     */
    public static function generatePlainToken(): string
    {
        return self::TOKEN_PREFIX.bin2hex(random_bytes(24));
    }

    /**
     * SHA-256 хеш токена для хранения.
     */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Короткий префикс для отображения в админке.
     */
    public static function prefixFromPlain(string $plain): string
    {
        return substr($plain, 0, 14).'…';
    }

    /**
     * Найти сервер по plaintext-токену.
     */
    public static function findByPlainToken(string $plain): ?self
    {
        $plain = trim($plain);

        if ($plain === '' || ! str_starts_with($plain, self::TOKEN_PREFIX)) {
            return null;
        }

        return static::query()->where('token_hash', self::hashToken($plain))->first();
    }

    /**
     * Выпустить новый токен (создание или ротация).
     */
    public function rotateToken(): string
    {
        $plain = self::generatePlainToken();

        $this->forceFill([
            'token_hash' => self::hashToken($plain),
            'token_prefix' => self::prefixFromPlain($plain),
        ])->save();

        return $plain;
    }

    /**
     * Однострочник установки агента.
     */
    public function installCommand(string $plainToken): string
    {
        $url = rtrim((string) config('app.url'), '/').'/agent/install.sh';

        return sprintf(
            'curl -fsSL %s | sudo bash -s -- --token %s',
            escapeshellarg($url),
            escapeshellarg($plainToken),
        );
    }

    /**
     * Настройки с дефолтами.
     *
     * @return array<string, mixed>
     */
    public function resolvedSettings(): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return array_merge(self::defaultSettings(), $settings);
    }

    /**
     * Значение настройки с дефолтом.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $settings = $this->resolvedSettings();

        return $settings[$key] ?? $default;
    }

    /**
     * Интервал heartbeat в секундах (для ответа агенту).
     */
    public function heartbeatIntervalSeconds(): int
    {
        $minutes = (int) $this->setting('heartbeat_interval_minutes', 10);

        return max(60, $minutes * 60);
    }

    /**
     * Проект.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * История heartbeat.
     *
     * @return HasMany<ServerHeartbeat, $this>
     */
    public function heartbeats(): HasMany
    {
        return $this->hasMany(ServerHeartbeat::class);
    }

    /**
     * Последний heartbeat.
     *
     * @return HasOne<ServerHeartbeat, $this>
     */
    public function latestHeartbeat(): HasOne
    {
        return $this->hasOne(ServerHeartbeat::class)->latestOfMany('reported_at');
    }

    /**
     * Привязки каналов уведомлений.
     *
     * @return HasMany<ServerNotificationChannel, $this>
     */
    public function notificationChannelAssignments(): HasMany
    {
        return $this->hasMany(ServerNotificationChannel::class);
    }
}
