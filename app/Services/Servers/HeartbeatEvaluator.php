<?php

namespace App\Services\Servers;

use App\DTO\HeartbeatEvaluation;

/**
 * Оценка метрик heartbeat по порогам (SRP).
 * CPU/load в статус не входят, пока явно не включены в settings.
 */
class HeartbeatEvaluator
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>|null  $previousValue
     */
    public function evaluate(array $payload, array $settings, ?array $previousValue = null): HeartbeatEvaluation
    {
        $issues = [
            ...$this->evaluateDisks($payload, $settings),
            ...$this->evaluateMemory($payload, $settings),
            ...$this->evaluateSwap($payload, $settings),
            ...$this->evaluateReboot($payload, $previousValue),
        ];

        if ((bool) ($settings['load_alert_enabled'] ?? false)) {
            $issues = [...$issues, ...$this->evaluateLoad($payload, $settings)];
        }

        $status = 'success';
        $reasons = [];

        foreach ($issues as $issue) {
            $reasons[] = $issue['message'];
            $status = $this->worseStatus($status, $issue['status']);
        }

        $message = $reasons === []
            ? $this->okMessage($payload)
            : implode('; ', $reasons);

        return new HeartbeatEvaluation(
            status: $status,
            message: $message,
            reasons: $reasons,
        );
    }

    public function silence(int $minutesWithoutPing): HeartbeatEvaluation
    {
        $minutesWithoutPing = max(1, $minutesWithoutPing);
        $message = "Нет пинга {$minutesWithoutPing} мин";

        return new HeartbeatEvaluation(
            status: 'failed',
            message: $message,
            reasons: [$message],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return list<array{status: string, message: string}>
     */
    protected function evaluateDisks(array $payload, array $settings): array
    {
        $disks = $payload['disks'] ?? [];

        if (! is_array($disks)) {
            return [];
        }

        $mounts = $settings['monitored_mounts'] ?? [];
        $monitored = is_array($mounts)
            ? array_values(array_filter($mounts, fn (mixed $mount): bool => is_string($mount) && $mount !== ''))
            : [];

        $issues = [];

        foreach ($disks as $disk) {
            if (! is_array($disk)) {
                continue;
            }

            $mount = is_string($disk['mount'] ?? null) ? $disk['mount'] : '?';

            if ($monitored !== [] && ! in_array($mount, $monitored, true)) {
                continue;
            }

            $total = (int) ($disk['total_bytes'] ?? 0);
            $used = (int) ($disk['used_bytes'] ?? 0);

            if ($total <= 0) {
                continue;
            }

            $free = max(0, $total - $used);
            $usedPercent = ($used / $total) * 100;
            $inodesTotal = (int) ($disk['inodes_total'] ?? 0);
            $inodesUsed = (int) ($disk['inodes_used'] ?? 0);
            $inodePercent = $inodesTotal > 0 ? ($inodesUsed / $inodesTotal) * 100 : 0.0;

            $criticalPercent = (float) ($settings['disk_critical_percent'] ?? 95);
            $warningPercent = (float) ($settings['disk_warning_percent'] ?? 85);
            $criticalFree = (int) ($settings['disk_critical_free_bytes'] ?? 512 * 1024 * 1024);
            $warningFree = (int) ($settings['disk_warning_free_bytes'] ?? 2 * 1024 * 1024 * 1024);
            $inodeCritical = (float) ($settings['inode_critical_percent'] ?? 95);
            $inodeWarning = (float) ($settings['inode_warning_percent'] ?? 80);

            $isCritical = $usedPercent >= $criticalPercent
                || $free <= $criticalFree
                || ($inodesTotal > 0 && $inodePercent >= $inodeCritical);

            $isWarning = $usedPercent >= $warningPercent
                || $free <= $warningFree
                || ($inodesTotal > 0 && $inodePercent >= $inodeWarning);

            if (! $isCritical && ! $isWarning) {
                continue;
            }

            $parts = [
                'занято '.round($usedPercent, 1).'%',
                'свободно '.$this->formatBytes($free),
            ];

            if ($inodesTotal > 0 && $inodePercent >= $inodeWarning) {
                $parts[] = 'inodes '.round($inodePercent, 1).'%';
            }

            $issues[] = [
                'status' => $isCritical ? 'failed' : 'warning',
                'message' => 'Диск '.$mount.': '.implode(', ', $parts),
            ];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return list<array{status: string, message: string}>
     */
    protected function evaluateMemory(array $payload, array $settings): array
    {
        $memory = $payload['memory'] ?? null;

        if (! is_array($memory)) {
            return [];
        }

        $total = (int) ($memory['total_bytes'] ?? 0);
        $available = (int) ($memory['available_bytes'] ?? 0);

        if ($total <= 0) {
            return [];
        }

        $availablePercent = ($available / $total) * 100;
        $criticalPercent = (float) ($settings['memory_critical_percent'] ?? 3);
        $warningPercent = (float) ($settings['memory_warning_percent'] ?? 10);
        $criticalBytes = (int) ($settings['memory_critical_available_bytes'] ?? 64 * 1024 * 1024);
        $warningBytes = (int) ($settings['memory_warning_available_bytes'] ?? 256 * 1024 * 1024);

        $isCritical = $availablePercent <= $criticalPercent || $available <= $criticalBytes;
        $isWarning = $availablePercent <= $warningPercent || $available <= $warningBytes;

        if (! $isCritical && ! $isWarning) {
            return [];
        }

        return [[
            'status' => $isCritical ? 'failed' : 'warning',
            'message' => 'Память: доступно '.round($availablePercent, 1).'% ('.$this->formatBytes($available).')',
        ]];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return list<array{status: string, message: string}>
     */
    protected function evaluateSwap(array $payload, array $settings): array
    {
        $memory = $payload['memory'] ?? null;

        if (! is_array($memory)) {
            return [];
        }

        $swapTotal = (int) ($memory['swap_total_bytes'] ?? 0);
        $swapFree = (int) ($memory['swap_free_bytes'] ?? 0);

        if ($swapTotal <= 0) {
            return [];
        }

        $swapUsed = max(0, $swapTotal - $swapFree);
        $usedPercent = ($swapUsed / $swapTotal) * 100;
        $criticalPercent = (float) ($settings['swap_critical_percent'] ?? 90);
        $warningPercent = (float) ($settings['swap_warning_percent'] ?? 50);

        if ($usedPercent < $warningPercent) {
            return [];
        }

        return [[
            'status' => $usedPercent >= $criticalPercent ? 'failed' : 'warning',
            'message' => 'Swap: занято '.round($usedPercent, 1).'%',
        ]];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $previousValue
     * @return list<array{status: string, message: string}>
     */
    protected function evaluateReboot(array $payload, ?array $previousValue): array
    {
        if ($previousValue === null) {
            return [];
        }

        $current = $payload['uptime_seconds'] ?? null;
        $previous = $previousValue['uptime_seconds'] ?? null;

        if (! is_numeric($current) || ! is_numeric($previous)) {
            return [];
        }

        if ((int) $current >= (int) $previous) {
            return [];
        }

        $minutes = max(1, (int) floor((int) $current / 60));

        return [[
            'status' => 'warning',
            'message' => "Сервер перезагрузился (аптайм {$minutes} мин)",
        ]];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return list<array{status: string, message: string}>
     */
    protected function evaluateLoad(array $payload, array $settings): array
    {
        $cpu = $payload['cpu'] ?? null;

        if (! is_array($cpu)) {
            return [];
        }

        $load15 = $cpu['load15'] ?? null;
        $cores = $cpu['cores'] ?? null;

        if (! is_numeric($load15) || ! is_numeric($cores) || (int) $cores <= 0) {
            return [];
        }

        $perCore = (float) $load15 / (int) $cores;
        $warning = (float) ($settings['load_warning_per_core'] ?? 2.0);

        if ($perCore < $warning) {
            return [];
        }

        return [[
            'status' => 'warning',
            'message' => 'Нагрузка: load15 '.round($perCore, 2).' на ядро',
        ]];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function okMessage(array $payload): string
    {
        $parts = [];

        $memory = is_array($payload['memory'] ?? null) ? $payload['memory'] : [];
        $total = (int) ($memory['total_bytes'] ?? 0);
        $available = (int) ($memory['available_bytes'] ?? 0);

        if ($total > 0) {
            $parts[] = 'RAM доступно '.round(($available / $total) * 100, 1).'%';
        }

        $disks = is_array($payload['disks'] ?? null) ? $payload['disks'] : [];

        foreach ($disks as $disk) {
            if (! is_array($disk)) {
                continue;
            }

            $diskTotal = (int) ($disk['total_bytes'] ?? 0);
            $used = (int) ($disk['used_bytes'] ?? 0);
            $mount = is_string($disk['mount'] ?? null) ? $disk['mount'] : '?';

            if ($diskTotal > 0) {
                $parts[] = 'диск '.$mount.' '.round(($used / $diskTotal) * 100, 1).'%';
            }
        }

        $cpu = is_array($payload['cpu'] ?? null) ? $payload['cpu'] : [];
        $load15 = $cpu['load15'] ?? null;
        $cores = $cpu['cores'] ?? null;

        if (is_numeric($load15) && is_numeric($cores) && (int) $cores > 0) {
            $parts[] = 'load '.round((float) $load15 / (int) $cores, 2);
        }

        return $parts === [] ? 'OK' : implode(', ', $parts);
    }

    protected function worseStatus(string $current, string $candidate): string
    {
        $rank = ['success' => 0, 'warning' => 1, 'failed' => 2];

        return ($rank[$candidate] ?? 0) > ($rank[$current] ?? 0) ? $candidate : $current;
    }

    protected function formatBytes(int $bytes): string
    {
        $bytes = max(0, $bytes);

        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 1).' ГБ';
        }

        if ($bytes >= 1024 * 1024) {
            return (string) round($bytes / (1024 * 1024)).' МБ';
        }

        if ($bytes >= 1024) {
            return (string) round($bytes / 1024).' КБ';
        }

        return $bytes.' Б';
    }
}
