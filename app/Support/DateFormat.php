<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use IntlDateFormatter;

/**
 * Renders an event `Carbon` instant in a chosen locale.
 *
 * Event dates are in-universe instants, not real-world timestamps, so this
 * class never calls `setTimezone()`. `isoFormat()` tokens (not `format()`)
 * pick month names, segment order, and 12/24h clock from the locale itself,
 * so no clock/order map is kept anywhere in this codebase.
 */
class DateFormat
{
    /**
     * A date only — "Mar 15, 1247" (en), "15 mars 1247" (fr).
     */
    public static function date(CarbonInterface $moment, LocaleChoice $locale): string
    {
        return $moment->locale($locale->carbon)->isoFormat('LL');
    }

    /**
     * A date and time — "Mar 15, 1247, 2:30 PM" (en), "15 mars 1247, 14:30" (fr).
     */
    public static function dateTime(CarbonInterface $moment, LocaleChoice $locale): string
    {
        return $moment->locale($locale->carbon)->isoFormat('LLL');
    }

    /**
     * The 12 month names of the locale, keyed 1-12 — the picker's month labels.
     *
     * @return array<int, string>
     */
    public static function monthNames(LocaleChoice $locale): array
    {
        $names = [];

        foreach (range(1, 12) as $month) {
            $names[$month] = Carbon::create(2000, $month, 1)
                ->locale($locale->carbon)
                ->isoFormat('MMMM');
        }

        return $names;
    }

    /**
     * The locale's date segment order, as 'dmy', 'mdy' or 'ymd'.
     *
     * Read from the locale's own ICU short-date pattern, so no order map is
     * kept in config. An unknown pattern falls back to 'dmy', the order most
     * of the offered locales use.
     */
    public static function segmentOrder(LocaleChoice $locale): string
    {
        $letters = preg_replace('/[^dMy]/', '', self::pattern($locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE)) ?? '';
        $order = '';

        foreach (str_split($letters) as $letter) {
            $segment = ['d' => 'd', 'M' => 'm', 'y' => 'y'][$letter];

            if (! str_contains($order, $segment)) {
                $order .= $segment;
            }
        }

        return in_array($order, ['dmy', 'mdy', 'ymd'], true) ? $order : 'dmy';
    }

    /**
     * Whether the locale writes the time on a 12-hour clock. ICU marks a
     * 12-hour pattern with `h` (1-12) or `K` (0-11).
     */
    public static function usesTwelveHourClock(LocaleChoice $locale): bool
    {
        $pattern = self::pattern($locale, IntlDateFormatter::NONE, IntlDateFormatter::SHORT);

        // Strip quoted literals first: a quoted 'h' is text, not a field.
        $fields = preg_replace("/'[^']*'/", '', $pattern) ?? '';

        return str_contains($fields, 'h') || str_contains($fields, 'K');
    }

    /** The locale's ICU pattern for the given date and time widths. */
    private static function pattern(LocaleChoice $locale, int $dateType, int $timeType): string
    {
        return (new IntlDateFormatter($locale->carbon, $dateType, $timeType))->getPattern() ?: '';
    }
}
