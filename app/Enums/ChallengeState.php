<?php

namespace App\Enums;

/**
 * Where a challenge sits relative to today. Derived at read time from the
 * window and `WriterDay::for()` — never stored, and this enum holds no
 * logic of its own; see App\Support\ChallengeWindow for the rule.
 */
enum ChallengeState: string
{
    case Upcoming = 'upcoming';
    case Running = 'running';
    case Finished = 'finished';
}
