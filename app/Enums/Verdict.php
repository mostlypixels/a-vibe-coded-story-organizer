<?php

namespace App\Enums;

/**
 * The judgement on a single contrast ratio, from App\Support\ColorContrast::verdict().
 *
 * Three outcomes, not a boolean, because the two failures are not the same problem:
 * `TooLow` is unreadable and rejected, `TooHigh` is glare (halation on dark themes)
 * and only ever warns — see the theme-switcher spec's contrast thresholds.
 */
enum Verdict: string
{
    case TooLow = 'too_low';
    case Ok = 'ok';
    case TooHigh = 'too_high';
}
