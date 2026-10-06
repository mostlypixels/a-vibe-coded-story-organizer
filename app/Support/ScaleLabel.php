<?php

namespace App\Support;

/** Human label for a scale or spacing value, such as `16px` or `1.15×`. */
final class ScaleLabel
{
    /**
     * @param  string  $format  `px` (percentage of 16px), `times` (multiplier);
     *                          any other format returns the value unchanged
     */
    public static function format(string $value, string $format): string
    {
        $isPercentage = str_ends_with($value, '%');
        $number = (float) rtrim($value, '%');
        $multiplier = $isPercentage ? $number / 100 : $number;

        return match ($format) {
            'px' => round($number / 100 * 16).'px',
            'times' => rtrim(rtrim(number_format($multiplier, 2), '0'), '.').'×',
            default => $value,
        };
    }
}
