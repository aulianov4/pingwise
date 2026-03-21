<?php

namespace App\Events;

use App\Models\Site;
use App\Models\TestResult;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Событие смены статуса теста.
 * Диспатчится из TestService при изменении статуса результата.
 */
class TestStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Site $site,
        public readonly TestResult $currentResult,
        public readonly ?TestResult $previousResult,
    ) {}
}
