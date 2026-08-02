<?php

namespace Tests\Unit;

use App\Enums\Verdict;
use App\Support\ColorContrast;
use App\Support\Oklch;
use PHPUnit\Framework\TestCase;

class ColorContrastTest extends TestCase
{
    public function test_black_on_white_is_the_maximum_ratio(): void
    {
        $this->assertEqualsWithDelta(21.0, ColorContrast::ratio('#000000', '#ffffff'), 0.01);
    }

    public function test_identical_colors_have_no_contrast(): void
    {
        $this->assertEqualsWithDelta(1.0, ColorContrast::ratio('#219ebc', '#219ebc'), 0.001);
    }

    /**
     * Published WCAG reference pairs: #767676 is the lightest gray passing 4.5:1 on
     * white, #595959 the lightest passing 7:1.
     */
    public function test_known_pairs_match_published_ratios(): void
    {
        $this->assertEqualsWithDelta(4.54, ColorContrast::ratio('#767676', '#ffffff'), 0.02);
        $this->assertEqualsWithDelta(7.0, ColorContrast::ratio('#595959', '#ffffff'), 0.02);
    }

    public function test_ratio_is_symmetric(): void
    {
        $this->assertSame(
            ColorContrast::ratio('#219ebc', '#ffffff'),
            ColorContrast::ratio('#ffffff', '#219ebc'),
        );
    }

    public function test_it_accepts_oklch_instances_and_hex_strings_interchangeably(): void
    {
        $this->assertEqualsWithDelta(
            ColorContrast::ratio('#0f766e', '#ffffff'),
            ColorContrast::ratio(Oklch::fromHex('#0f766e'), Oklch::fromHex('#ffffff')),
            0.001,
        );
    }

    public function test_verdict_rejects_a_ratio_under_the_floor_for_its_kind(): void
    {
        // 3.5:1 fails as text but passes as a border or focus ring.
        $this->assertSame(Verdict::TooLow, ColorContrast::verdict(3.5, isText: true, ceiling: 15.0));
        $this->assertSame(Verdict::Ok, ColorContrast::verdict(3.5, isText: false, ceiling: 15.0));

        $this->assertSame(Verdict::TooLow, ColorContrast::verdict(2.9, isText: false, ceiling: 15.0));
    }

    public function test_verdict_accepts_a_ratio_inside_the_band(): void
    {
        $this->assertSame(Verdict::Ok, ColorContrast::verdict(8.0, isText: true, ceiling: 15.0));
    }

    /**
     * The ceiling is per-preset config, so the same ratio must be able to come out
     * either way depending on which preset is being authored.
     */
    public function test_two_ceilings_give_different_verdicts_for_the_same_ratio(): void
    {
        $this->assertSame(Verdict::TooHigh, ColorContrast::verdict(12.0, isText: true, ceiling: 10.0));
        $this->assertSame(Verdict::Ok, ColorContrast::verdict(12.0, isText: true, ceiling: 18.0));
    }

    /**
     * A ceiling under the applicable floor would make every ratio both too low and too
     * high; the floor wins, so the verdict stays decidable.
     */
    public function test_a_ceiling_below_the_floor_is_raised_to_it(): void
    {
        $this->assertSame(Verdict::Ok, ColorContrast::verdict(4.5, isText: true, ceiling: 2.0));
        $this->assertSame(Verdict::TooHigh, ColorContrast::verdict(4.6, isText: true, ceiling: 2.0));
    }
}
