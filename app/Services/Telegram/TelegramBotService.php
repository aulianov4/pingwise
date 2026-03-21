<?php

namespace App\Services\Telegram;

use App\Models\TelegramChat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Сервис взаимодействия с Telegram Bot API (SRP).
 * Ответственность: HTTP-запросы к Telegram API, синхронизация чатов, отправка сообщений.
 */
class TelegramBotService implements TelegramBotInterface
{
    protected string $baseUrl;

    public function __construct(
        protected readonly string $token,
    ) {
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function isConfigured(): bool
    {
        return ! empty($this->token);
    }

    /**
     * Синхронизировать список групп/супергрупп из Telegram API.
     * Вызывает getUpdates, парсит чаты и upsert в БД.
     */
    public function syncChats(): Collection
    {
        if (! $this->isConfigured()) {
            Log::warning('Telegram bot token is not configured');

            return collect();
        }

        try {
            $response = Http::get("{$this->baseUrl}/getUpdates", [
                'allowed_updates' => json_encode(['message', 'my_chat_member']),
            ]);

            if (! $response->successful()) {
                Log::error('Telegram getUpdates failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return collect();
            }

            $updates = $response->json('result', []);
            $chats = $this->extractChatsFromUpdates($updates);

            // Upsert чатов в БД
            $telegramChats = collect();
            foreach ($chats as $chatData) {
                $chat = TelegramChat::updateOrCreate(
                    ['chat_id' => $chatData['id']],
                    [
                        'title' => $chatData['title'] ?? "Chat {$chatData['id']}",
                        'type' => $chatData['type'],
                    ]
                );
                $telegramChats->push($chat);
            }

            return $telegramChats;
        } catch (\Exception $e) {
            Log::error('Failed to sync Telegram chats: '.$e->getMessage());

            return collect();
        }
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
            $response = Http::post("{$this->baseUrl}/sendMessage", [
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

    /**
     * Извлечь уникальные группы/супергруппы/каналы из обновлений
     */
    protected function extractChatsFromUpdates(array $updates): array
    {
        $chats = [];

        foreach ($updates as $update) {
            // Из обычных сообщений
            if (isset($update['message']['chat'])) {
                $chat = $update['message']['chat'];
                if (in_array($chat['type'], ['group', 'supergroup', 'channel'])) {
                    $chats[$chat['id']] = $chat;
                }
            }

            // Из событий my_chat_member (когда бота добавляют в группу)
            if (isset($update['my_chat_member']['chat'])) {
                $chat = $update['my_chat_member']['chat'];
                if (in_array($chat['type'], ['group', 'supergroup', 'channel'])) {
                    $chats[$chat['id']] = $chat;
                }
            }
        }

        return $chats;
    }
}
