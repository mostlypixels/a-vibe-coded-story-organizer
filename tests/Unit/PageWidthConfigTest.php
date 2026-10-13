<?php

namespace Tests\Unit;

use Tests\TestCase;

class PageWidthConfigTest extends TestCase
{
    public function test_the_default_is_one_of_the_steps(): void
    {
        $this->assertContains(config('page_width.default'), config('page_width.steps'));
    }

    public function test_the_steps_ascend_so_the_switcher_can_walk_them(): void
    {
        $steps = config('page_width.steps');
        $sorted = $steps;
        sort($sorted);

        $this->assertSame($sorted, $steps);
        $this->assertSame($steps, array_values(array_unique($steps)));
    }

    public function test_every_step_is_a_percentage_from_1_to_100(): void
    {
        foreach (config('page_width.steps') as $step) {
            $this->assertIsInt($step);
            $this->assertGreaterThanOrEqual(1, $step);
            $this->assertLessThanOrEqual(100, $step);
        }
    }
}
