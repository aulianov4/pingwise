<?php

use App\Http\Controllers\AgentScriptController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/telegram/webhook/{secret}', TelegramWebhookController::class)
    ->name('telegram.webhook');

Route::get('/agent/install.sh', [AgentScriptController::class, 'install'])
    ->name('agent.install');
Route::get('/agent/heartbeat.sh', [AgentScriptController::class, 'heartbeat'])
    ->name('agent.heartbeat');

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Ошибка подключения к базе данных',
        ], 503);
    }
});
