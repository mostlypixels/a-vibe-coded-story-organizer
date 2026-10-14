<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Enums\SceneStatus;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\EntityLink;
use App\Models\Event;
use App\Models\Note;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\Services\CodexEntryDuplicator;
use App\Services\CodexEntrySaver;
use App\Services\EntityLinkIndex;
use App\Services\FieldAutosaver;
use App\Services\SceneDuplicator;
use App\Support\CodexMediaUploads;
use App\Support\FieldHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** `entity_links` must equal the links in the saved text, on every write path. */
class EntityLinkIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    private Book $book;

    private Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        [$this->project, $this->book] = $this->projectWithBook($this->owner);
        $this->chapter = Chapter::factory()->for(Act::factory()->for($this->book))->create();
    }

    private function html(string ...$hrefs): string
    {
        return implode('', array_map(fn (string $href): string => '<p><a href="'.$href.'">x</a></p>', $hrefs));
    }

    /** @return list<string> "field:type/id" for each row of the source. */
    private function rowsOf(object $source): array
    {
        return EntityLink::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (EntityLink $row): string => "{$row->source_field}:{$row->target_type}/{$row->target_id}")
            ->all();
    }

    private function rowsTo(string $type, int $id): int
    {
        return EntityLink::query()->where('target_type', $type)->where('target_id', $id)->count();
    }

    public function test_a_save_with_two_links_writes_two_rows_and_an_edit_replaces_them(): void
    {
        $note = Note::factory()->for($this->project)->create(['body' => $this->html('entity:scene/7', 'entity:codex/8')]);

        $this->assertSame(['body:scene/7', 'body:codex/8'], $this->rowsOf($note));
        $this->assertSame($this->project->id, EntityLink::query()->value('project_id'));

        $note->update(['body' => $this->html('entity:codex/8')]);

        $this->assertSame(['body:codex/8'], $this->rowsOf($note));
    }

    public function test_a_repeated_link_writes_one_row(): void
    {
        $note = Note::factory()->for($this->project)->create(['body' => $this->html('entity:scene/7', 'entity:scene/7')]);

        $this->assertSame(['body:scene/7'], $this->rowsOf($note));
    }

    public function test_scene_contents_and_description_are_both_indexed(): void
    {
        $scene = Scene::factory()->for($this->chapter)->create([
            'description' => $this->html('entity:note/3'),
            'contents' => 'See [the oath](entity:event/4).',
        ]);

        $this->assertEqualsCanonicalizing(['description:note/3', 'contents:event/4'], $this->rowsOf($scene));
    }

    public function test_a_save_that_does_not_touch_a_link_field_keeps_the_rows(): void
    {
        $note = Note::factory()->for($this->project)->create(['body' => $this->html('entity:scene/7')]);
        EntityLink::query()->update(['target_id' => 99]);

        $note->fresh()->update(['title' => 'Renamed']);

        $this->assertSame(['body:scene/99'], $this->rowsOf($note));
    }

    public function test_deleting_the_source_removes_its_rows(): void
    {
        $note = Note::factory()->for($this->project)->create(['body' => $this->html('entity:scene/7')]);

        $note->delete();

        $this->assertSame(0, EntityLink::query()->count());
    }

    public function test_deleting_the_target_removes_the_rows_that_point_at_it(): void
    {
        $event = Event::factory()->for($this->project)->create();
        $note = Note::factory()->for($this->project)->create(['body' => $this->html("entity:event/{$event->id}", 'entity:scene/7')]);

        $event->delete();

        $this->assertSame(['body:scene/7'], $this->rowsOf($note));
    }

    public function test_deleting_a_book_removes_the_rows_that_point_at_it(): void
    {
        $other = Book::factory()->for($this->project)->create();
        Note::factory()->for($this->project)->create(['body' => $this->html("entity:book/{$other->id}")]);

        $other->delete();

        $this->assertSame(0, $this->rowsTo('book', $other->id));
    }

    public function test_deleting_a_chapter_removes_the_rows_of_its_scenes_as_source_and_as_target(): void
    {
        $scene = Scene::factory()->for($this->chapter)->create(['contents' => '[x](entity:codex/5)']);
        $note = Note::factory()->for($this->project)->create(['body' => $this->html("entity:scene/{$scene->id}", 'entity:codex/5')]);

        $this->chapter->delete();

        $this->assertSame([], $this->rowsOf($scene));
        $this->assertSame(['body:codex/5'], $this->rowsOf($note));
    }

    public function test_deleting_an_act_removes_the_rows_of_its_chapters_and_scenes(): void
    {
        $act = $this->chapter->act;
        $this->chapter->update(['description' => $this->html('entity:codex/5')]);
        $scene = Scene::factory()->for($this->chapter)->create(['contents' => '[x](entity:codex/5)']);
        Note::factory()->for($this->project)->create(['body' => $this->html("entity:chapter/{$this->chapter->id}", "entity:scene/{$scene->id}")]);

        $act->delete();

        $this->assertSame(0, EntityLink::query()->count());
    }

    public function test_deleting_a_book_removes_the_rows_of_its_acts_chapters_and_scenes(): void
    {
        $act = $this->chapter->act;
        $act->update(['description' => $this->html('entity:codex/5')]);
        $scene = Scene::factory()->for($this->chapter)->create(['contents' => '[x](entity:codex/5)']);
        Note::factory()->for($this->project)->create(['body' => $this->html("entity:act/{$act->id}", "entity:scene/{$scene->id}")]);
        // A second book, so the first one can be deleted.
        Book::factory()->for($this->project)->create();

        $this->book->delete();

        $this->assertSame(0, EntityLink::query()->count());
    }

    public function test_deleting_the_project_removes_every_row(): void
    {
        Note::factory()->for($this->project)->create(['body' => $this->html('entity:scene/7')]);

        $this->project->delete();

        $this->assertSame(0, EntityLink::query()->count());
    }

    public function test_the_form_update_indexes_the_links(): void
    {
        $note = Note::factory()->for($this->project)->create(['body' => '<p>Old</p>']);

        $this->actingAs($this->owner)->put(route('notes.update', $note), [
            'title' => 'After',
            'body' => $this->html('entity:scene/7'),
            'base_hashes' => ['body' => hash('sha256', '<p>Old</p>')],
        ])->assertRedirect();

        $this->assertSame(['body:scene/7'], $this->rowsOf($note));
    }

    public function test_a_non_owner_form_update_writes_no_rows(): void
    {
        $note = Note::factory()->for($this->project)->create(['body' => '<p>Old</p>']);

        $this->actingAs(User::factory()->create())->put(route('notes.update', $note), [
            'title' => 'After',
            'body' => $this->html('entity:scene/7'),
            'base_hashes' => ['body' => hash('sha256', '<p>Old</p>')],
        ])->assertForbidden();

        $this->assertSame(0, EntityLink::query()->count());
    }

    public function test_the_scene_form_update_indexes_the_contents(): void
    {
        $scene = Scene::factory()->for($this->chapter)->create(['contents' => 'Old']);

        $this->actingAs($this->owner)->put(route('scenes.update', $scene), [
            'chapter_id' => $this->chapter->id,
            'name' => 'Opening scene',
            'description' => null,
            'contents' => 'Now [linked](entity:note/3).',
            'status' => SceneStatus::Draft->value,
        ])->assertRedirect();

        $this->assertSame(['contents:note/3'], $this->rowsOf($scene));
    }

    public function test_an_autosave_indexes_the_links(): void
    {
        $act = Act::factory()->for($this->book)->create(['description' => '<p>Old</p>']);

        app(FieldAutosaver::class)->save($act, 'description', $this->html('entity:codex/5'), FieldHash::of('<p>Old</p>'), $this->owner);

        $this->assertSame(['description:codex/5'], $this->rowsOf($act));
    }

    public function test_the_codex_entry_saver_indexes_the_description(): void
    {
        $entry = app(CodexEntrySaver::class)->create(
            $this->project,
            CodexEntryType::Character,
            ['name' => 'Raymondin', 'description' => $this->html('entity:scene/7')],
            new CodexMediaUploads,
        );

        $this->assertSame(['description:scene/7'], $this->rowsOf($entry));
    }

    public function test_a_revision_revert_indexes_the_restored_text(): void
    {
        $note = Note::factory()->for($this->project)->create(['body' => $this->html('entity:scene/7')]);
        $old = Revision::factory()->create([
            'revisionable_type' => $note->getMorphClass(),
            'revisionable_id' => $note->id,
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'field' => 'body',
            'value' => $this->html('entity:codex/8'),
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->owner)->post(route('revisions.revert', $old), [
            'base_hash' => FieldHash::of($note->body),
        ])->assertRedirect();

        $this->assertSame(['body:codex/8'], $this->rowsOf($note));
    }

    public function test_a_duplicated_scene_indexes_its_copy(): void
    {
        $scene = Scene::factory()->for($this->chapter)->create(['contents' => '[x](entity:codex/5)']);

        $copy = app(SceneDuplicator::class)->duplicate($scene, 'Copy');

        $this->assertSame(['contents:codex/5'], $this->rowsOf($copy));
        $this->assertSame(['contents:codex/5'], $this->rowsOf($scene));
    }

    public function test_a_duplicated_codex_entry_indexes_its_copy(): void
    {
        $entry = CodexEntry::factory()->for($this->project)->create(['description' => $this->html('entity:scene/7')]);

        $copy = app(CodexEntryDuplicator::class)->duplicate($entry, 'Copy');

        $this->assertSame(['description:scene/7'], $this->rowsOf($copy));
    }

    public function test_sync_project_rebuilds_rows_that_model_events_skipped(): void
    {
        $scene = Scene::withoutEvents(fn () => Scene::factory()->for($this->chapter)->create(['contents' => '[x](entity:note/3)']));
        $this->project->update(['description' => $this->html('entity:scene/7')]);
        EntityLink::query()->delete();

        app(EntityLinkIndex::class)->syncProject($this->project);

        $this->assertSame(['contents:note/3'], $this->rowsOf($scene));
        $this->assertSame(['description:scene/7'], $this->rowsOf($this->project));
    }

    public function test_sync_project_leaves_other_projects_alone(): void
    {
        [$other] = $this->projectWithBook($this->owner);
        $foreign = Note::factory()->for($other)->create(['body' => $this->html('entity:scene/7')]);

        app(EntityLinkIndex::class)->syncProject($this->project);

        $this->assertSame(['body:scene/7'], $this->rowsOf($foreign));
    }
}
