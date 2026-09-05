<?php

namespace Tests\Unit\Support;

use App\Support\DateFormat;
use App\Support\LocaleChoice;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * DateFormat delegates to Carbon isoFormat() tokens, so segment order, month
 * names and 12/24h clock all come from the locale, not a hand-kept map.
 */
class DateFormatTest extends TestCase
{
    public function test_date_formats_in_english(): void
    {
        $moment = Carbon::create(1247, 3, 15, 14, 30);

        $this->assertSame('March 15, 1247', DateFormat::date($moment, LocaleChoice::resolve('en')));
    }

    public function test_date_time_formats_in_english(): void
    {
        $moment = Carbon::create(1247, 3, 15, 14, 30);

        $this->assertSame('March 15, 1247 2:30 PM', DateFormat::dateTime($moment, LocaleChoice::resolve('en')));
    }

    public function test_date_formats_in_french(): void
    {
        $moment = Carbon::create(1247, 3, 15, 14, 30);

        $this->assertSame('15 mars 1247', DateFormat::date($moment, LocaleChoice::resolve('fr')));
    }

    public function test_date_time_formats_in_french(): void
    {
        $moment = Carbon::create(1247, 3, 15, 14, 30);

        $this->assertSame('15 mars 1247 14:30', DateFormat::dateTime($moment, LocaleChoice::resolve('fr')));
    }

    public function test_formatting_is_identical_regardless_of_the_users_timezone_column(): void
    {
        $moment = Carbon::create(1247, 3, 15, 14, 30);
        $locale = LocaleChoice::resolve('en');

        // DateFormat never calls setTimezone(), so the users.timezone column
        // (used only for real-world dates) has no bearing on event display.
        $before = DateFormat::dateTime($moment, $locale);
        config(['app.timezone' => 'America/New_York']);
        $after = DateFormat::dateTime($moment, $locale);

        $this->assertSame($before, $after);
    }

    public function test_month_names_come_from_the_locale(): void
    {
        $this->assertSame('March', DateFormat::monthNames(LocaleChoice::resolve('en'))[3]);
        $this->assertSame('mars', DateFormat::monthNames(LocaleChoice::resolve('fr'))[3]);
    }

    public function test_segment_order_comes_from_the_locale_pattern(): void
    {
        $this->assertSame('dmy', DateFormat::segmentOrder(LocaleChoice::resolve('fr')));
        $this->assertSame('mdy', DateFormat::segmentOrder(LocaleChoice::resolve('en-US')));
    }

    public function test_the_clock_comes_from_the_locale_pattern(): void
    {
        $this->assertTrue(DateFormat::usesTwelveHourClock(LocaleChoice::resolve('en-US')));
        $this->assertFalse(DateFormat::usesTwelveHourClock(LocaleChoice::resolve('fr')));
    }
}
