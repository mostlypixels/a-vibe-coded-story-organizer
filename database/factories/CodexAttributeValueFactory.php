<?php

namespace Database\Factories;

use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodexAttributeValue>
 */
class CodexAttributeValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Resolve the entry first so the attribute and the start event both
        // belong to the same project. Callers that need a specific event or
        // attribute should override with ->for(...) / ->startingAt(...).
        return [
            'codex_entry_id' => CodexEntry::factory(),
            'codex_attribute_id' => fn (array $attributes) => CodexAttribute::factory()->for(
                CodexEntry::find($attributes['codex_entry_id'])->project
            ),
            'start_event_id' => fn (array $attributes) => CodexEntry::find($attributes['codex_entry_id'])
                ->project->events()->where('title', 'Start')->value('id'),
            'value' => fake()->word(),
        ];
    }

    /**
     * Anchor the value at a specific start event.
     */
    public function startingAt(Event $event): static
    {
        return $this->state(['start_event_id' => $event->id]);
    }
}
