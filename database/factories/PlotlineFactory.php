<?php

namespace Database\Factories;

use App\Models\Plotline;
use App\Models\Project;
use App\Support\PlotlineColors;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plotline>
 */
class PlotlineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Assign colors by a process-wide round-robin instead of at random.
        // Two plotlines in one project may never share a color (the
        // unique(project_id, color) constraint), and random draws collided
        // intermittently (~10% for ->count(3)) — making tests flaky. A DB-based
        // de-dup can't prevent this: it is skipped when project_id arrives via
        // ->for() (absent from the closure's $attributes) and it cannot see
        // not-yet-persisted siblings created in the same ->count() batch.
        //
        // The counter MUST live in the method body, not in a `color` closure:
        // ->count(N) calls definition() once per instance, and a `static` in a
        // method is shared across those calls, whereas each per-instance closure
        // gets its own `static` (so all N would pick index 0 and collide).
        // Round-robin is collision-free for any realistic per-project plotline
        // count (fewer than the palette size). PRESETS[0] is excluded because
        // Project::booted() reserves it for the auto-created main plotline.
        static $colorIndex = 0;
        $palette = array_slice(PlotlineColors::PRESETS, 1);
        $color = $palette[$colorIndex++ % count($palette)];

        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'color' => $color,
        ];
    }
}
