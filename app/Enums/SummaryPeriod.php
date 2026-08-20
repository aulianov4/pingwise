<?php

namespace App\Enums;

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
}
