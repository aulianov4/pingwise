<?php

namespace Tests\Unit\Services;

use App\Services\Availability\AvailabilityIncidentEvaluator;
use Tests\TestCase;

class AvailabilityIncidentEvaluatorTest extends TestCase
{
    public function test_not_down_until_three_failures(): void
    {
        $evaluator = new AvailabilityIncidentEvaluator;

        $this->assertFalse($evaluator->isDownFromFailures([true]));
        $this->assertFalse($evaluator->isDownFromFailures([true, true]));
        $this->assertTrue($evaluator->isDownFromFailures([true, true, true]));
    }

    public function test_uses_only_last_five_probes(): void
    {
        $evaluator = new AvailabilityIncidentEvaluator;

        $this->assertFalse($evaluator->isDownFromFailures([
            false, false, false, true, true, true, true,
        ]));
        $this->assertTrue($evaluator->isDownFromFailures([
            true, true, true, false, false,
        ]));
    }

    public function test_summarize_counts_window(): void
    {
        $evaluator = new AvailabilityIncidentEvaluator;

        $this->assertSame([
            'incident_down' => true,
            'window_failures' => 3,
            'window_size' => 4,
        ], $evaluator->summarize([true, false, true, true]));
    }
}
