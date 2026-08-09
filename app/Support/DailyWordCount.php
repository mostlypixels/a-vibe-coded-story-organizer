<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * One calendar day of a project's word history: the cumulative total at the
 * end of the day, and how many words the day added.
 *
 * `written` is derived, never stored — it is this day's total minus the
 * previous day's. It can be negative, because a writer can cut text.
 */
class DailyWordCount
{
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly int $total,
        public readonly int $written,
    ) {}
}
