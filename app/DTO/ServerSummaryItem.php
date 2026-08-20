<?php

namespace App\DTO;

use App\Models\Server;
use Illuminate\Support\Carbon;

/**
 * Строка саммари по одному серверу за период.
 */
readonly class ServerSummaryItem
{
    /**
     * @param  list<string>  $warningReasons
     * @param  list<string>  $failedReasons
     */
    public function __construct(
        public Server $server,
        public bool $awaitingAgent,
        public bool $currentlySilent,
        public ?string $status,
        public ?string $lastMessage,
        public ?Carbon $lastSeenAt,
        public ?Carbon $silentSince,
        public ?int $silentMinutes,
        public ?float $ramAvailablePercent,
        public ?float $diskUsedPercent,
        public ?int $uptimeSeconds,
        public int $silenceIncidents,
        public int $warningCount,
        public int $failedCount,
        public array $warningReasons,
        public array $failedReasons,
        public int $rebootCount,
    ) {}
}
