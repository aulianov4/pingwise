<?php

namespace App\Models;

use Database\Factories\ServerHeartbeatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Снимок метрик или синтетическая запись тишины.
 */
class ServerHeartbeat extends Model
{
    /** @use HasFactory<ServerHeartbeatFactory> */
    use HasFactory;

    public const SOURCE_HEARTBEAT = 'heartbeat';

    public const SOURCE_SILENCE = 'silence';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_id',
        'source',
        'status',
        'value',
        'message',
        'reported_at',
    ];

    protected $casts = [
        'value' => 'array',
        'reported_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Доля доступной RAM, 0–100.
     */
    public function memoryAvailablePercent(): ?float
    {
        $total = $this->value['memory']['total_bytes'] ?? null;
        $available = $this->value['memory']['available_bytes'] ?? null;

        if (! is_numeric($total) || (int) $total <= 0 || ! is_numeric($available)) {
            return null;
        }

        return round(((int) $available / (int) $total) * 100, 1);
    }

    /**
     * Максимальный процент занятости среди дисков.
     */
    public function worstDiskUsedPercent(): ?float
    {
        $disks = $this->value['disks'] ?? null;

        if (! is_array($disks) || $disks === []) {
            return null;
        }

        $worst = null;

        foreach ($disks as $disk) {
            if (! is_array($disk)) {
                continue;
            }

            $total = $disk['total_bytes'] ?? null;
            $used = $disk['used_bytes'] ?? null;

            if (! is_numeric($total) || (int) $total <= 0 || ! is_numeric($used)) {
                continue;
            }

            $percent = ((int) $used / (int) $total) * 100;
            $worst = $worst === null ? $percent : max($worst, $percent);
        }

        return $worst === null ? null : round($worst, 1);
    }

    /**
     * Load15 на одно ядро.
     */
    public function loadPerCore(): ?float
    {
        $load15 = $this->value['cpu']['load15'] ?? null;
        $cores = $this->value['cpu']['cores'] ?? null;

        if (! is_numeric($load15) || ! is_numeric($cores) || (int) $cores <= 0) {
            return null;
        }

        return round((float) $load15 / (int) $cores, 2);
    }

    public function isSilence(): bool
    {
        return $this->source === self::SOURCE_SILENCE;
    }
}
