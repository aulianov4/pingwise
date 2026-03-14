<?php
namespace App\Console\Commands;
use App\Services\Telegram\TelegramBotInterface;
use Illuminate\Console\Command;
class SyncTelegramChatsCommand extends Command
{
    protected $signature = 'pingwise:telegram:sync';
    protected $description = 'Синхронизировать список Telegram-групп бота';
    public function handle(TelegramBotInterface $bot): int
    {
        if (! $bot->isConfigured()) {
            $this->warn('Telegram bot token не настроен. Добавьте TELEGRAM_BOT_TOKEN в .env');
            return self::FAILURE;
        }
        $this->info('Синхронизация Telegram-чатов...');
        $chats = $bot->syncChats();
        if ($chats->isEmpty()) {
            $this->warn('Группы не найдены. Убедитесь, что бот добавлен в группу и в ней было хотя бы одно сообщение.');
            return self::SUCCESS;
        }
        $this->info("Найдено групп: {$chats->count()}");
        foreach ($chats as $chat) {
            $this->line("  • {$chat->title} (ID: {$chat->chat_id}, тип: {$chat->type})");
        }
        return self::SUCCESS;
    }
}
