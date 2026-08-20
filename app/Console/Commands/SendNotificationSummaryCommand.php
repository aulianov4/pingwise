<?php

namespace App\Console\Commands;

use App\Enums\SummaryPeriod;
use App\Models\NotificationChannel;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Command;

class SendNotificationSummaryCommand extends Command
{
    protected $signature = 'pingwise:notifications:summary
                            {--period= : daily, weekly или monthly; с --due можно не указывать}
                            {--due : только каналы, у которых сейчас время саммари}';

    protected $description = 'Отправить саммари уведомлений по каналам за период';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $due = (bool) $this->option('due');
        $periodValue = $this->option('period');
        $periodValue = is_string($periodValue) && $periodValue !== '' ? $periodValue : null;

        if (! $due && $periodValue === null) {
            $periodValue = SummaryPeriod::Daily->value;
        }

        $clock = NotificationChannel::summaryNow();
        $atTime = $due ? $clock->format('H:i') : null;
        $periods = $this->resolvePeriods($due, $periodValue, $clock);

        if ($periods === []) {
            $this->error('Неизвестный период. Используйте daily, weekly или monthly.');

            return self::FAILURE;
        }

        $sent = 0;

        foreach ($periods as $period) {
            $count = $dispatcher->sendSummaries($period, $atTime);
            $sent += $count;
            $this->info("Саммари ({$period->value}) отправлено в {$count} канал(ов).");
        }

        if (count($periods) > 1) {
            $this->info("Всего сообщений: {$sent}.");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<SummaryPeriod>
     */
    protected function resolvePeriods(bool $due, ?string $periodValue, \DateTimeInterface $clock): array
    {
        if ($periodValue !== null) {
            $period = SummaryPeriod::tryFrom($periodValue);

            return $period === null ? [] : [$period];
        }

        return $due ? SummaryPeriod::dueAt($clock) : [SummaryPeriod::Daily];
    }
}
