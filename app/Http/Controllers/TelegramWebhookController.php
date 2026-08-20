<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Входящие апдейты Telegram Bot API.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, TelegramConnectService $connectService): JsonResponse
    {
        if (! hash_equals((string) config('services.telegram.webhook_secret'), $secret)) {
            abort(403);
        }

        $payload = $request->all();

        if ($payload !== []) {
            $connectService->handleUpdate($payload);
        }

        return response()->json(['ok' => true]);
    }
}
