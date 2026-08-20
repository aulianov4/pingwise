<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServerHeartbeatRequest;
use App\Services\Servers\ServerHeartbeatService;
use Illuminate\Http\JsonResponse;

class ServerHeartbeatController extends Controller
{
    public function store(StoreServerHeartbeatRequest $request, ServerHeartbeatService $heartbeats): JsonResponse
    {
        $server = $request->monitoredServer();

        if ($server === null) {
            abort(401, 'Недействительный токен агента.');
        }

        $heartbeat = $heartbeats->ingest($server, $request->payload());
        $server->refresh();

        return response()->json([
            'ok' => true,
            'status' => $heartbeat->status,
            'message' => $heartbeat->message,
            'next_interval_seconds' => $server->heartbeatIntervalSeconds(),
        ]);
    }
}
