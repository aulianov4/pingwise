<?php

namespace Tests\Unit;

use App\Enums\SummaryPeriod;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SummaryPeriodTest extends TestCase
{
    public function test_due_at_weekday_is_only_daily(): void
    {
        $periods = SummaryPeriod::dueAt(Carbon::parse('2026-08-21 09:00:00'));

        $this->assertSame(
            [SummaryPeriod::Daily],
            $periods,
        );
    }

    public function test_due_at_monday_includes_weekly(): void
    {
        $periods = SummaryPeriod::dueAt(Carbon::parse('2026-08-24 09:00:00'));

        $this->assertSame(
            [SummaryPeriod::Daily, SummaryPeriod::Weekly],
            $periods,
        );
    }

    public function test_due_at_monday_first_includes_all(): void
    {
        $periods = SummaryPeriod::dueAt(Carbon::parse('2026-06-01 09:00:00'));

        $this->assertSame(
            [SummaryPeriod::Daily, SummaryPeriod::Weekly, SummaryPeriod::Monthly],
            $periods,
        );
    }
}
