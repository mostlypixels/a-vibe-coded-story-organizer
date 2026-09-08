<?php

namespace Tests\Feature;

use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Run the cleanup migration against raw pairs with and without a filled value. */
class DeleteBlankCodexAttributePairsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runCleanupMigration(): void
    {
        /** @var Migration $migration */
        $migration = include database_path('migrations/2026_09_08_000000_delete_blank_codex_attribute_pairs.php');

        $migration->up();
    }

    public function test_it_deletes_an_all_blank_pair_and_keeps_a_blank_then_filled_pair(): void
    {
        $project = Project::factory()->create();
        $entry = CodexEntry::factory()->for($project)->create();

        $blankAttribute = CodexAttribute::factory()->for($project)->create();
        $blankValue = CodexAttributeValue::factory()
            ->for($entry, 'entry')
            ->for($blankAttribute, 'attribute')
            ->startingAt($project->startEvent())
            ->create(['value' => '']);

        $timelineAttribute = CodexAttribute::factory()->for($project)->create();
        $baselineValue = CodexAttributeValue::factory()
            ->for($entry, 'entry')
            ->for($timelineAttribute, 'attribute')
            ->startingAt($project->startEvent())
            ->create(['value' => '']);
        $laterEvent = Event::factory()->for($project)->create();
        $filledValue = CodexAttributeValue::factory()
            ->for($entry, 'entry')
            ->for($timelineAttribute, 'attribute')
            ->startingAt($laterEvent)
            ->create(['value' => 'known later']);

        $this->runCleanupMigration();

        $this->assertDatabaseMissing('codex_attribute_values', ['id' => $blankValue->id]);
        $this->assertModelExists($baselineValue);
        $this->assertModelExists($filledValue);
        $this->assertModelExists($blankAttribute);
        $this->assertModelExists($timelineAttribute);
    }

    public function test_it_is_safe_to_run_when_every_pair_has_a_value(): void
    {
        $value = CodexAttributeValue::factory()->create(['value' => 'known']);

        $this->runCleanupMigration();

        $this->assertModelExists($value);
        $this->assertDatabaseCount('codex_attribute_values', 1);
    }
}
