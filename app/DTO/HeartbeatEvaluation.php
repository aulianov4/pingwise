<?php

namespace App\DTO;

/**
 * Результат оценки heartbeat без привязки к Eloquent.
 */
readonly class HeartbeatEvaluation
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public string $status,
        public string $message,
        public array $reasons = [],
    ) {}
}
