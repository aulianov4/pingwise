<?php

namespace App\Console\Commands;

use App\Enums\SummaryPeriod;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Command;

class SendNotificationSummaryCommand extends Command
{
    protected $signature = 'pingwise:notifications:summary {--period=daily : daily, weekly или monthly}';

    protected $description = 'Отправить саммари уведомлений по каналам за период';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $periodValue = (string) $this->option('period');
        $period = SummaryPeriod::tryFrom($periodValue);

        if ($period === null) {
            $this->error('Неизвестный период. Используйте daily, weekly или monthly.');

            return self::FAILURE;
        }

        $sent = $dispatcher->sendSummaries($period);
        $this->info("Саммари ({$period->value}) отправлено в {$sent} канал(ов).");

        return self::SUCCESS;
    }
}
