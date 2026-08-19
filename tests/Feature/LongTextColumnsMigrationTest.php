<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Verify each autosaved text field accepts more than 65,535 bytes. */
class LongTextColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, list<string>> */
    private function registeredColumns(): array
    {
        return [
            'projects' => ['description'],
            'books' => ['description', 'dedication', 'acknowledgements', 'preface', 'postface', 'rights'],
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
        // loop documents the registered set and will fail loudly on a
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

    public function test_project_description_round_trips_a_payload_larger_than_the_old_mysql_text_cap(): void
    {
        $longValue = str_repeat('b', 100_000);

        $project = Project::factory()->create(['description' => $longValue]);

        $fresh = Project::find($project->id);

        $this->assertSame(100_000, strlen($fresh->description));
        $this->assertSame($longValue, $fresh->description);
    }

    public function test_book_dedication_round_trips_a_payload_larger_than_the_old_mysql_text_cap(): void
    {
        // `books` was created with longText() columns rather than widened later,
        // so this guards the create migration against a copy of the old text().
        $longValue = str_repeat('h', 100_000);

        $book = Book::factory()->create(['dedication' => $longValue]);

        $this->assertSame($longValue, Book::find($book->id)->dedication);
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
