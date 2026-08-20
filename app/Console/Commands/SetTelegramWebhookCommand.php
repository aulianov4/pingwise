<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotInterface;
use Illuminate\Console\Command;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'pingwise:telegram:set-webhook';

    protected $description = 'Установить Telegram webhook для привязки групп через /connect';

    public function handle(TelegramBotInterface $bot): int
    {
        if (! $bot->isConfigured()) {
            $this->error('Telegram bot token не настроен. Добавьте TELEGRAM_BOT_TOKEN в .env');

            return self::FAILURE;
        }

        $secret = (string) config('services.telegram.webhook_secret');

        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET не задан в .env');

            return self::FAILURE;
        }

        $url = rtrim((string) config('app.url'), '/').'/telegram/webhook/'.$secret;
        $this->info("Установка webhook: {$url}");

        if (! $bot->setWebhook($url)) {
            $this->error('Не удалось установить webhook.');
            $this->warn('Если api.telegram.org недоступен (блокировка в РФ), задайте TELEGRAM_PROXY или TELEGRAM_API_BASE_URL в .env.');

            return self::FAILURE;
        }

        $this->info('Webhook установлен.');

        return self::SUCCESS;
    }
}
