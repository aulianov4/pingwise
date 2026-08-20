<?php

namespace Tests\Unit;

use App\Enums\SummaryPeriod;
use App\Models\NotificationChannel;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationChannelSummaryTimeTest extends TestCase
{
    public function test_is_summary_due_matches_moscow_clock(): void
    {
        $this->travelTo(Carbon::parse('2026-08-21 06:00:00', 'UTC'));

        $channel = new NotificationChannel(['summary_time' => '09:00']);

        $this->assertTrue($channel->isSummaryDue());
        $this->assertSame('09:00', NotificationChannel::summaryNow()->format('H:i'));
    }

    public function test_is_summary_due_ignores_utc_wall_clock(): void
    {
        $this->travelTo(Carbon::parse('2026-08-21 09:00:00', 'UTC'));

        $channel = new NotificationChannel(['summary_time' => '09:00']);

        $this->assertFalse($channel->isSummaryDue());
        $this->assertSame('12:00', NotificationChannel::summaryNow()->format('H:i'));
    }

    public function test_due_periods_use_moscow_calendar(): void
    {
        $sundayUtc = Carbon::parse('2026-08-23 21:00:00', 'UTC');
        $clock = NotificationChannel::summaryNow($sundayUtc);

        $this->assertTrue($clock->isMonday());
        $this->assertSame(
            [SummaryPeriod::Daily, SummaryPeriod::Weekly],
            SummaryPeriod::dueAt($clock),
        );
    }
}
