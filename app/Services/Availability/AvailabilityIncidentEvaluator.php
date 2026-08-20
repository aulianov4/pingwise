<?php

namespace App\Services\Availability;

use App\Models\TestResult;

/**
 * Инцидент доступности по скользящему окну проб (SRP).
 */
class AvailabilityIncidentEvaluator
{
    public const WINDOW_SIZE = 5;

    public const DOWN_THRESHOLD = 3;

    /**
     * Сайт недоступен, если в последних пробах (макс. WINDOW_SIZE) неудач ≥ DOWN_THRESHOLD.
     *
     * @param  list<bool>  $failedNewestFirst
     */
    public function isDownFromFailures(array $failedNewestFirst): bool
    {
        $window = array_slice(array_values($failedNewestFirst), 0, self::WINDOW_SIZE);
        $failures = 0;

        foreach ($window as $failed) {
            if ($failed) {
                $failures++;
            }
        }

        return $failures >= self::DOWN_THRESHOLD;
    }

    public function isProbeFailed(TestResult $result): bool
    {
        return $this->isProbeFailedFromValue($result->value, $result->status);
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    public function isProbeFailedFromValue(?array $value, string $status): bool
    {
        if (is_array($value) && array_key_exists('is_up', $value) && is_bool($value['is_up'])) {
            return $value['is_up'] === false;
        }

        return $status === 'failed';
    }

    /**
     * @param  list<bool>  $failedNewestFirst
     * @return array{incident_down: bool, window_failures: int, window_size: int}
     */
    public function summarize(array $failedNewestFirst): array
    {
        $window = array_slice(array_values($failedNewestFirst), 0, self::WINDOW_SIZE);
        $failures = 0;

        foreach ($window as $failed) {
            if ($failed) {
                $failures++;
            }
        }

        return [
            'incident_down' => $failures >= self::DOWN_THRESHOLD,
            'window_failures' => $failures,
            'window_size' => count($window),
        ];
    }
}
