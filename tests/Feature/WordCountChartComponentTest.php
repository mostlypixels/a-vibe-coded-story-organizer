<?php

namespace Tests\Feature;

use App\Support\DailyWordCount;
use App\Support\WordCountSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The `<x-word-count-chart>` component, rendered standalone via
 * `Blade::render()` following {@see DiffComponentTest}'s precedent.
 *
 * The chart itself is drawn by `resources/js/word-count-chart.js` and tested in
 * `resources/js/word-count-chart.test.js`. What this asserts is the contract
 * between the two: the canvas the module mounts on, and the series the module
 * reads out of `x-data`.
 */
class WordCountChartComponentTest extends TestCase
{
    private function series(int ...$written): WordCountSeries
    {
        $date = CarbonImmutable::parse('2026-03-01');
        $total = 0;

        return new WordCountSeries(collect($written)->map(function (int $count, int $index) use ($date, &$total) {
            $total += $count;

            return new DailyWordCount(
                date: $date->addDays($index),
                total: $total,
                written: $count,
            );
        }));
    }

    private function render(WordCountSeries $series, string $extra = ''): string
    {
        return Blade::render(
            '<x-word-count-chart :series="$series" '.$extra.' />',
            ['series' => $series],
        );
    }

    public function test_it_renders_a_canvas_the_alpine_component_can_mount_on(): void
    {
        $rendered = $this->render($this->series(800));

        $this->assertStringContainsString('x-data="wordCountChart(', $rendered);
        $this->assertStringContainsString('x-ref="canvas"', $rendered);
        $this->assertStringContainsString('<canvas', $rendered);
        // A canvas carries no text of its own.
        $this->assertStringContainsString('role="img"', $rendered);
    }

    public function test_it_serialises_one_labelled_entry_per_day(): void
    {
        $rendered = $this->render($this->series(800, 0, -120));

        $this->assertStringContainsString('1 Mar', $rendered);
        $this->assertStringContainsString('3 Mar', $rendered);
        $this->assertStringContainsString('800', $rendered);
        $this->assertStringContainsString('-120', $rendered);
    }

    public function test_the_daily_goal_is_null_unless_the_project_sets_one(): void
    {
        $this->assertStringContainsString('dailyGoal: null', $this->render($this->series(800)));
        $this->assertStringContainsString('dailyGoal: 500', $this->render($this->series(800), ':daily-goal="500"'));
    }

    public function test_the_variant_reaches_both_the_component_and_the_markup(): void
    {
        $full = $this->render($this->series(800));
        $compact = $this->render($this->series(800), 'variant="compact"');

        $this->assertStringContainsString('data-variant="full"', $full);
        $this->assertStringContainsString("variant: 'full'", $full);
        $this->assertStringContainsString('data-variant="compact"', $compact);
        $this->assertStringContainsString("variant: 'compact'", $compact);
    }
}
