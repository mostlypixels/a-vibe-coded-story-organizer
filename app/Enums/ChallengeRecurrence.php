<?php

namespace App\Enums;

/**
 * How a challenge's window repeats. A `Monthly` challenge stores one row;
 * its window is derived — the calendar month containing the day being
 * asked about — never materialised into occurrence rows.
 */
enum ChallengeRecurrence: string
{
    case None = 'none';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::None => 'One-off',
            self::Monthly => 'Every month',
        };
    }
}
