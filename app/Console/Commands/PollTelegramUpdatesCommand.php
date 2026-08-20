<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotInterface;
use App\Services\Telegram\TelegramConnectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PollTelegramUpdatesCommand extends Command
{
    protected $signature = 'pingwise:telegram:poll {--timeout=25 : Long poll timeout, seconds}';

    protected $description = 'Забрать входящие сообщения бота через getUpdates (обход блокировки webhook)';

    private const OFFSET_CACHE_KEY = 'telegram.update_offset';

    public function handle(TelegramBotInterface $bot, TelegramConnectService $connectService): int
    {
        if (! $bot->isConfigured()) {
            $this->error('Telegram bot token не настроен.');

            return self::FAILURE;
        }

        $timeout = max(0, (int) $this->option('timeout'));
        $offset = Cache::get(self::OFFSET_CACHE_KEY);
        $offset = is_numeric($offset) ? (int) $offset : null;

        $updates = $bot->getUpdates($offset, $timeout);

        foreach ($updates as $update) {
            $connectService->handleUpdate($update);

            $updateId = $update['update_id'] ?? null;

            if (is_numeric($updateId)) {
                Cache::forever(self::OFFSET_CACHE_KEY, (int) $updateId + 1);
            }
        }

        $this->info('Обработано обновлений: '.count($updates));

        return self::SUCCESS;
    }
}
