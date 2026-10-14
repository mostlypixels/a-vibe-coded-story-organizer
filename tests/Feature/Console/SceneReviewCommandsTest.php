<?php

namespace Tests\Feature\Console;

use App\Enums\RevisionOrigin;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAlias;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Project;
use App\Models\Scene;
use App\Services\SceneReferenceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneReviewCommandsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();
        $act = Act::factory()->for($this->project->books()->first())->create();
        $this->chapter = Chapter::factory()->for($act)->create(['name' => 'Chapter One']);
    }

    private function scene(string $name, string $contents = 'Some text.'): Scene
    {
        return Scene::factory()->for($this->chapter)->create(['name' => $name, 'contents' => $contents]);
    }

    private function bodyFile(string $html): string
    {
        $path = tempnam(sys_get_temp_dir(), 'review');
        file_put_contents($path, $html);

        return $path;
    }

    public function test_review_note_creates_the_todo_category_and_links_the_note(): void
    {
        $scene = $this->scene('The Fountain');

        $this->artisan('scene:review-note', ['scene' => $scene->id, '--file' => $this->bodyFile('<h2>Spelling</h2><ul><li>fix</li></ul>')])
            ->assertSuccessful();

        $category = NoteCategory::query()->where('project_id', $this->project->id)->where('name', 'TODO')->sole();
        $note = $scene->notes()->sole();
        $this->assertSame($category->id, $note->note_category_id);
        $this->assertSame('Review: The Fountain', $note->title);
        $this->assertStringContainsString('<li>fix</li>', $note->body);
    }

    public function test_review_note_reuses_an_existing_todo_category(): void
    {
        $existing = NoteCategory::factory()->create(['project_id' => $this->project->id, 'name' => 'todo']);
        $scene = $this->scene('The Fountain');

        $this->artisan('scene:review-note', ['scene' => $scene->id, '--file' => $this->bodyFile('<p>fix</p>')])->assertSuccessful();

        $this->assertSame(1, NoteCategory::query()->where('project_id', $this->project->id)->count());
        $this->assertSame($existing->id, $scene->notes()->sole()->note_category_id);
    }

    public function test_a_second_review_replaces_the_note_and_keeps_a_revision(): void
    {
        $scene = $this->scene('The Fountain');

        $this->artisan('scene:review-note', ['scene' => $scene->id, '--file' => $this->bodyFile('<p>first</p>')])->assertSuccessful();
        $scene->update(['name' => 'The Well']);
        $this->artisan('scene:review-note', ['scene' => $scene->id, '--file' => $this->bodyFile('<p>second</p>')])->assertSuccessful();

        $note = $scene->notes()->sole();
        $this->assertSame('Review: The Well', $note->title);
        $this->assertSame('<p>second</p>', $note->body);
        $this->assertTrue($note->revisions()->where('field', 'body')->where('origin', RevisionOrigin::Manual)->where('value', '<p>second</p>')->exists());
        $this->assertTrue($note->revisions()->where('field', 'body')->where('value', '<p>first</p>')->exists());
    }

    public function test_review_note_leaves_other_scene_notes_alone(): void
    {
        $scene = $this->scene('The Fountain');
        $other = Note::factory()->create(['project_id' => $this->project->id, 'title' => 'Ideas', 'body' => '<p>keep</p>']);
        $other->linkTo($scene);

        $this->artisan('scene:review-note', ['scene' => $scene->id, '--file' => $this->bodyFile('<p>fix</p>')])->assertSuccessful();

        $this->assertSame(2, $scene->notes()->count());
        $this->assertSame('<p>keep</p>', $other->fresh()->body);
    }

    public function test_review_note_refuses_an_empty_body(): void
    {
        $scene = $this->scene('The Fountain');

        $this->artisan('scene:review-note', ['scene' => $scene->id, '--file' => $this->bodyFile('  ')])->assertFailed();

        $this->assertSame(0, Note::query()->count());
    }

    public function test_a_scene_name_with_several_matches_lists_them_and_fails(): void
    {
        $first = $this->scene('Dawn at sea');
        $second = $this->scene('Dawn at home');

        $this->artisan('scene:review-context', ['scene' => 'Dawn'])
            ->expectsOutputToContain((string) $first->id)
            ->expectsOutputToContain((string) $second->id)
            ->assertFailed();
    }

    public function test_an_exact_name_wins_over_partial_matches(): void
    {
        $this->scene('Dawn at sea');
        $this->scene('Dawn');

        $this->artisan('scene:review-context', ['scene' => 'dawn'])->assertSuccessful();
    }

    public function test_review_context_prints_codex_names_notes_and_earlier_scenes(): void
    {
        $melusine = CodexEntry::factory()->for($this->project)->create(['name' => 'Melusine']);
        CodexAlias::factory()->create(['codex_entry_id' => $melusine->id, 'alias' => 'the serpent lady']);
        $this->scene('Opening', 'The first scene text.');
        $middle = $this->scene('Middle', 'The middle scene text.');
        $scene = $this->scene('Reviewed', 'Melusine bathes.');
        $note = Note::factory()->create(['project_id' => $this->project->id, 'title' => 'Known issue', 'body' => '<p>Fix the bath.</p>']);
        $note->linkTo($scene);

        $this->artisan('scene:review-context', ['scene' => $scene->id, '--full' => 1])
            ->expectsOutputToContain('- Melusine (Character) — aliases: the serpent lady')
            ->expectsOutputToContain('Known issue')
            ->expectsOutputToContain("{$middle->id}  Middle")
            ->expectsOutputToContain('The middle scene text.')
            ->doesntExpectOutputToContain('The first scene text.')
            ->expectsOutputToContain('Melusine bathes.')
            ->assertSuccessful();
    }

    public function test_review_context_marks_only_entries_outside_their_lifespan(): void
    {
        $moment = Event::factory()->create(['project_id' => $this->project->id, 'event_datetime' => '1830-06-01 12:00']);
        $death = Event::factory()->create(['project_id' => $this->project->id, 'event_datetime' => '1820-01-01 12:00']);
        CodexEntry::factory()->for($this->project)->create(['name' => 'Tisiphone', 'description' => '<p>The heroine.</p>']);
        CodexEntry::factory()->for($this->project)->create(['name' => 'Ghost', 'termination_event_id' => $death->id]);
        $scene = $this->scene('Ball', 'Tisiphone dances with Ghost.');
        $scene->update(['event_id' => $moment->id]);
        app(SceneReferenceMatcher::class)->syncScene($scene);

        $this->artisan('scene:review-context', ['scene' => $scene->id])
            ->expectsOutputToContain('The heroine.')
            ->expectsOutputToContain('- Ghost: DOES NOT EXIST at this moment')
            ->doesntExpectOutputToContain('- Tisiphone: DOES NOT EXIST')
            ->assertSuccessful();
    }

    public function test_scene_show_prints_the_contents(): void
    {
        $scene = $this->scene('Opening', 'The first scene text.');

        $this->artisan('scene:show', ['scenes' => [$scene->id]])
            ->expectsOutputToContain('The first scene text.')
            ->assertSuccessful();
    }

    public function test_scene_search_finds_text_in_this_project_only(): void
    {
        $scene = $this->scene('Opening', 'The dragon sleeps.');
        $otherProject = Project::factory()->create();
        $otherChapter = Chapter::factory()->for(Act::factory()->for($otherProject->books()->first()))->create();
        Scene::factory()->for($otherChapter)->create(['name' => 'Elsewhere', 'contents' => 'Another dragon.']);

        $this->artisan('scene:search', ['project' => $this->project->id, 'query' => 'dragon'])
            ->expectsOutputToContain("{$scene->id}  Opening")
            ->expectsOutputToContain('[dragon]')
            ->doesntExpectOutputToContain('Elsewhere')
            ->assertSuccessful();
    }
}
