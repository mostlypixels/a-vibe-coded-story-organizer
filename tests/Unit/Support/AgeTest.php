<?php

namespace Tests\Unit\Support;

use App\Support\Age;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AgeTest extends TestCase
{
    public function test_full_years_between_two_dates(): void
    {
        $age = Age::between(
            CarbonImmutable::parse('1980-01-01'),
            CarbonImmutable::parse('2000-01-01'),
        );

        $this->assertSame(20, $age->years);
    }

    public function test_floors_when_the_birthday_has_not_happened_yet_this_year(): void
    {
        $age = Age::between(
            CarbonImmutable::parse('1980-06-01'),
            CarbonImmutable::parse('2000-01-01'),
        );

        $this->assertSame(19, $age->years);
    }

    public function test_a_moment_before_inception_cuts_toward_zero(): void
    {
        $justBefore = Age::between(
            CarbonImmutable::parse('2000-06-01'),
            CarbonImmutable::parse('2000-01-01'),
        );
        $wellBefore = Age::between(
            CarbonImmutable::parse('2000-06-01'),
            CarbonImmutable::parse('1998-01-01'),
        );

        $this->assertSame(0, $justBefore->years);
        $this->assertSame(-2, $wellBefore->years);
    }
}
