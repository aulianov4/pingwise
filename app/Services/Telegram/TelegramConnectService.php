<?php

namespace App\Services\Telegram;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Log;

/**
 * Привязка Telegram-группы к каналу по коду /connect (SRP).
 */
class TelegramConnectService
{
    /**
     * @var list<string>
     */
    private const ALLOWED_CHAT_TYPES = ['group', 'supergroup', 'channel'];

    public function __construct(
        protected readonly TelegramBotInterface $bot,
    ) {}

    /**
     * Обработать входящий апдейт Telegram.
     *
     * @param  array<string, mixed>  $update
     */
    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        $text = $message['text'] ?? '';
        $chat = $message['chat'] ?? null;

        if (! is_string($text) || ! is_array($chat)) {
            return;
        }

        if (! preg_match('/^(?:@\S+\s+)?\/connect(?:@\S+)?(?:\s+(\S+))?/i', trim($text), $matches)) {
            return;
        }

        $chatId = (int) ($chat['id'] ?? 0);
        $chatType = (string) ($chat['type'] ?? '');
        $title = (string) ($chat['title'] ?? $chat['username'] ?? "Chat {$chatId}");

        $token = $matches[1] ?? null;

        if (! is_string($token) || $token === '') {
            $this->bot->sendMessage($chatId, 'Укажите код из админки: /connect PW-XXXX');

            return;
        }

        if (! in_array($chatType, self::ALLOWED_CHAT_TYPES, true)) {
            $this->bot->sendMessage($chatId, 'Код нужно отправить в группе, куда добавлен бот.');

            return;
        }

        $channel = NotificationChannel::findByConnectToken($token);

        if ($channel === null) {
            $this->bot->sendMessage($chatId, 'Код не найден или истёк. Выпустите новый код в админке PingWise.');

            return;
        }

        $alreadyBound = NotificationChannel::query()
            ->where('project_id', $channel->project_id)
            ->where('telegram_chat_id', $chatId)
            ->whereKeyNot($channel->id)
            ->exists();

        if ($alreadyBound) {
            $this->bot->sendMessage($chatId, 'Эта группа уже привязана к другому каналу этого проекта.');

            return;
        }

        try {
            $channel->loadMissing('project');
            $channel->connectTelegram($chatId, $title, $chatType);

            $projectName = $channel->project?->name ?? 'проект';
            $this->bot->sendMessage(
                $chatId,
                "Канал «{$channel->name}» привязан к проекту «{$projectName}».",
            );
        } catch (\Exception $e) {
            Log::error('Failed to connect Telegram channel: '.$e->getMessage());
            $this->bot->sendMessage($chatId, 'Не удалось привязать группу. Попробуйте ещё раз.');
        }
    }
}
