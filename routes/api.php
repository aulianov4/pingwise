<?php

use App\Http\Controllers\Api\ServerHeartbeatController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/servers/heartbeat', [ServerHeartbeatController::class, 'store'])
    ->middleware(['server.agent', 'throttle:server-heartbeat'])
    ->name('api.servers.heartbeat');
