<?php

namespace App\Events;

use App\Models\Server;
use App\Models\ServerHeartbeat;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Смена статуса сервера (heartbeat или тишина).
 */
class ServerStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Server $server,
        public readonly ServerHeartbeat $currentHeartbeat,
        public readonly ?ServerHeartbeat $previousHeartbeat,
    ) {}
}
