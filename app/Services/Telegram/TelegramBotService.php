<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Сервис взаимодействия с Telegram Bot API (SRP).
 * Ответственность: HTTP-запросы к Telegram API и отправка сообщений.
 */
class TelegramBotService implements TelegramBotInterface
{
    protected string $baseUrl;

    public function __construct(
        protected readonly string $token,
        protected readonly string $apiBaseUrl = 'https://api.telegram.org',
        protected readonly ?string $proxy = null,
    ) {
        $host = $this->apiBaseUrl !== '' ? $this->apiBaseUrl : 'https://api.telegram.org';
        $this->baseUrl = rtrim($host, '/').'/bot'.$this->token;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->token);
    }

    /**
     * Отправить сообщение в чат
     */
    public function sendMessage(int $chatId, string $text, string $parseMode = 'HTML'): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Telegram bot token is not configured, message not sent');

            return false;
        }

        try {
            $response = $this->http()->post("{$this->baseUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Log::error('Telegram sendMessage failed', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send Telegram message to chat {$chatId}: ".$e->getMessage());

            return false;
        }
    }

    public function setWebhook(string $url): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Telegram bot token is not configured, webhook not set');

            return false;
        }

        try {
            $response = $this->http()->post("{$this->baseUrl}/setWebhook", [
                'url' => $url,
                'allowed_updates' => json_encode(['message']),
                'drop_pending_updates' => true,
            ]);

            if (! $response->successful() || ! $response->json('ok')) {
                Log::error('Telegram setWebhook failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to set Telegram webhook: '.$e->getMessage());

            return false;
        }
    }

    public function deleteWebhook(bool $dropPendingUpdates = true): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->http()->post("{$this->baseUrl}/deleteWebhook", [
                'drop_pending_updates' => $dropPendingUpdates,
            ]);

            return $response->successful() && (bool) $response->json('ok');
        } catch (\Exception $e) {
            Log::error('Failed to delete Telegram webhook: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUpdates(?int $offset = null, int $timeout = 0): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $updates = $this->requestUpdates($offset, $timeout);

            return $updates ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get Telegram updates: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    protected function requestUpdates(?int $offset, int $timeout): ?array
    {
        $payload = [
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message']),
        ];

        if ($offset !== null) {
            $payload['offset'] = $offset;
        }

        $response = $this->http(max(30, $timeout + 15))->post("{$this->baseUrl}/getUpdates", $payload);

        if ($response->status() === 409) {
            $this->deleteWebhook(dropPendingUpdates: false);
            $response = $this->http(max(30, $timeout + 15))->post("{$this->baseUrl}/getUpdates", $payload);
        }

        if (! $response->successful() || ! $response->json('ok')) {
            Log::error('Telegram getUpdates failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $result = $response->json('result');

        if (! is_array($result)) {
            return [];
        }

        return array_values(array_filter($result, 'is_array'));
    }

    protected function http(?int $timeout = null): PendingRequest
    {
        $request = Http::timeout($timeout ?? 30)->connectTimeout(20);

        if (filled($this->proxy)) {
            $request = $request->withOptions(['proxy' => $this->proxy]);
        }

        return $request;
    }
}
