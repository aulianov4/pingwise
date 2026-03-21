<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\TestResult;
use App\Services\Telegram\TelegramBotInterface;
use App\Services\Telegram\TelegramMessageFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailySummaryCommand extends Command
{
    protected $signature = 'pingwise:telegram:summary';

    protected $description = 'Отправить ежесуточное саммари в Telegram';

    public function handle(TelegramBotInterface $bot, TelegramMessageFormatter $formatter): int
    {
        if (! $bot->isConfigured()) {
            $this->warn('Telegram bot token не настроен.');

            return self::FAILURE;
        }
        $sites = Site::where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->with('telegramChat')
            ->get()
            ->filter(fn (Site $site) => $site->isTelegramSummaryEnabled());
        if ($sites->isEmpty()) {
            $this->info('Нет сайтов с включённым ежесуточным саммари.');

            return self::SUCCESS;
        }
        $sentCount = 0;
        foreach ($sites as $site) {
            $results = TestResult::where('site_id', $site->id)
                ->where('checked_at', '>=', now()->subDay())
                ->get();
            if ($results->isEmpty()) {
                continue;
            }
            try {
                $message = $formatter->formatDailySummary($site, $results);
                $sent = $bot->sendMessage($site->telegramChat->chat_id, $message);
                if ($sent) {
                    $sentCount++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to send daily summary for site {$site->id}: ".$e->getMessage());
                $this->error("Ошибка для {$site->name}: {$e->getMessage()}");
            }
        }
        $this->info("Саммари отправлено для {$sentCount} сайтов.");

        return self::SUCCESS;
    }
}
