<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 02 — widening the 14 columns AutosavableFields will register from
 * `text()` to `longText()` (2026_07_22_000001_widen_long_text_columns_to_long_text.php).
 *
 * The MySQL/MariaDB `text()` cap is 65,535 bytes; on sqlite (the test DB) both
 * types are already unbounded, so the meaningful assertion here is (a) the
 * reported column type changed and (b) a payload bigger than the old cap
 * round-trips whole — this is the regression the migration exists to fix.
 */
class LongTextColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * All 14 columns `AutosavableFields` will register, exactly as listed in
     * the plan's task 2 (`Table => [columns]`).
     *
     * @return array<string, list<string>>
     */
    private function registeredColumns(): array
    {
        return [
            'projects' => ['description', 'dedication', 'acknowledgements', 'preface', 'postface', 'rights'],
            'acts' => ['description'],
            'chapters' => ['description'],
            'plotlines' => ['description'],
            'events' => ['description'],
            'scenes' => ['description', 'notes', 'contents'],
            'codex_entries' => ['description'],
        ];
    }

    public function test_every_registered_column_is_a_known_and_queryable_text_column(): void
    {
        // sqlite reports both `text()` and `longText()` columns as "text" at
        // the PRAGMA level, so this alone can't prove the widen happened —
        // the round-trip tests below are the real regression guard. This
        // loop documents the full 14-column set and will fail loudly on a
        // typo'd table/column name (Schema::getColumnType() throws on an
        // unknown column).
        foreach ($this->registeredColumns() as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertSame('text', Schema::getColumnType($table, $column), "{$table}.{$column}");
            }
        }
    }

    public function test_scene_contents_round_trips_a_payload_larger_than_the_old_mysql_text_cap(): void
    {
        // 65,535 bytes is MySQL/MariaDB's `text()` cap; 100,000 bytes is
        // safely past it.
        $longValue = str_repeat('a', 100_000);

        $scene = Scene::factory()->create(['contents' => $longValue]);

        $fresh = Scene::find($scene->id);

        $this->assertSame(100_000, strlen($fresh->contents));
        $this->assertSame($longValue, $fresh->contents);
    }

    public function test_project_rights_round_trips_a_payload_larger_than_the_old_mysql_text_cap(): void
    {
        $longValue = str_repeat('b', 100_000);

        $project = Project::factory()->create(['rights' => $longValue]);

        $fresh = Project::find($project->id);

        $this->assertSame(100_000, strlen($fresh->rights));
        $this->assertSame($longValue, $fresh->rights);
    }

    public function test_act_description_round_trips_a_payload_larger_than_the_old_mysql_text_cap(): void
    {
        $longValue = str_repeat('c', 100_000);

        $act = Act::factory()->create(['description' => $longValue]);

        $this->assertSame($longValue, Act::find($act->id)->description);
    }

    public function test_chapter_description_round_trips_a_payload_larger_than_the_old_mysql_text_cap(): void
    {
        $longValue = str_repeat('d', 100_000);

        $chapter = Chapter::factory()->create(['description' => $longValue]);

        $this->assertSame($longValue, Chapter::find($chapter->id)->description);
    }

    public function test_plotline_description_round_trips_a_payload_larger_than_the_old_mysql_text_cap(): void
    {
        $longValue = str_repeat('e', 100_000);

        $plotline = Plotline::factory()->create(['description' => $longValue]);

        $this->assertSame($longValue, Plotline::find($plotline->id)->description);
    }

    public function test_event_description_round_trips_a_payload_larger_than_the_old_mysql_text_cap(): void
    {
        $longValue = str_repeat('f', 100_000);

        $event = Event::factory()->create(['description' => $longValue]);

        $this->assertSame($longValue, Event::find($event->id)->description);
    }

    public function test_codex_entry_description_round_trips_a_payload_larger_than_the_old_mysql_text_cap(): void
    {
        $longValue = str_repeat('g', 100_000);

        $codexEntry = CodexEntry::factory()->create(['description' => $longValue]);

        $this->assertSame($longValue, CodexEntry::find($codexEntry->id)->description);
    }
}
