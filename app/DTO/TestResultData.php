<?php

namespace App\DTO;

/**
 * DTO для передачи результатов теста без привязки к Eloquent.
 */
readonly class TestResultData
{
    public function __construct(
        public string $status,
        public ?array $value = null,
        public ?string $message = null,
    ) {}
}
