<?php

namespace Tests\Unit\Support;

use App\Support\PageWidth;
use Tests\TestCase;

class PageWidthTest extends TestCase
{
    public function test_steps_returns_the_configured_allow_list(): void
    {
        $this->assertSame(config('page_width.steps'), PageWidth::steps());
    }

    public function test_resolve_returns_the_default_for_null(): void
    {
        $this->assertSame(70, PageWidth::resolve(null));
    }

    public function test_resolve_returns_each_step_unchanged(): void
    {
        foreach ([50, 60, 70, 80, 90, 100] as $step) {
            $this->assertSame($step, PageWidth::resolve($step));
        }
    }

    public function test_resolve_returns_the_default_for_values_off_the_list(): void
    {
        foreach ([55, 0, 110] as $value) {
            $this->assertSame(70, PageWidth::resolve($value));
        }
    }

    public function test_css_turns_a_step_into_a_percentage(): void
    {
        $this->assertSame('70%', PageWidth::css(70));
        $this->assertSame('100%', PageWidth::css(100));
    }
}
