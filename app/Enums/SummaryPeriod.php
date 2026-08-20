<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum SummaryPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * Человекочитаемое название периода.
     */
    public function label(): string
    {
        return match ($this) {
            SummaryPeriod::Daily => 'сутки',
            SummaryPeriod::Weekly => 'неделю',
            SummaryPeriod::Monthly => 'месяц',
        };
    }

    /**
     * Имя pivot-флага на привязке теста к каналу.
     */
    public function pivotFlag(): string
    {
        return match ($this) {
            SummaryPeriod::Daily => 'daily_summary',
            SummaryPeriod::Weekly => 'weekly_summary',
            SummaryPeriod::Monthly => 'monthly_summary',
        };
    }

    /**
     * Периоды, которые нужно отправить в этот момент (день недели / число месяца).
     * Календарь берётся из переданного момента — вызывающий код передаёт московское время.
     *
     * @return list<self>
     */
    public static function dueAt(\DateTimeInterface $now): array
    {
        $now = Carbon::parse($now);
        $periods = [self::Daily];

        if ($now->isMonday()) {
            $periods[] = self::Weekly;
        }

        if ($now->day === 1) {
            $periods[] = self::Monthly;
        }

        return $periods;
    }
}
