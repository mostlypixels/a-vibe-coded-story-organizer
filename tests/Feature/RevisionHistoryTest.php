<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 10 — RevisionController::index()/compare(): the history and compare
 * views for one (entity, id, field) triple (expanded/ui.md "History page"/
 * "Compare view", handoff.md §5.1/§5.3).
 */
class RevisionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function actFor(User $user): Act
    {
        $project = Project::factory()->for($user)->create();

        return Act::factory()->for($project)->create();
    }

    private function sceneFor(User $user): Scene
    {
        $act = $this->actFor($user);
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create();
    }

    private function revisionFor(Act $act, array $overrides = []): Revision
    {
        return Revision::factory()->create(array_merge([
            'revisionable_type' => Act::class,
            'revisionable_id' => $act->id,
            'project_id' => $act->project->id,
            'field' => 'description',
        ], $overrides));
    }

    // ---------------------------------------------------------------------
    // History index
    // ---------------------------------------------------------------------

    public function test_history_index_lists_revisions_newest_first(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->revisionFor($act, [
            'user_id' => $user->id,
            'label' => 'Older entry',
            'created_at' => now()->subMinutes(10),
        ]);
        $this->revisionFor($act, [
            'user_id' => $user->id,
            'label' => 'Newer entry',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description'])
        );

        $response->assertOk();
        $response->assertSeeInOrder(['Newer entry', 'Older entry']);
    }

    public function test_label_search_filters_the_history_list(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->revisionFor($act, ['user_id' => $user->id, 'label' => 'Alpha checkpoint']);
        $this->revisionFor($act, ['user_id' => $user->id, 'label' => 'Beta checkpoint']);

        $response = $this->actingAs($user)->get(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description', 'label' => 'Alpha'])
        );

        $response->assertOk();
        $response->assertSee('Alpha checkpoint');
        $response->assertDontSee('Beta checkpoint');
    }

    public function test_history_index_never_renders_the_stored_revision_value(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $marker = 'SECRET-REVISION-VALUE-MARKER-'.str_repeat('x', 200);
        $this->revisionFor($act, ['user_id' => $user->id, 'value' => "<p>{$marker}</p>"]);

        $response = $this->actingAs($user)->get(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description'])
        );

        $response->assertOk();
        $response->assertDontSee($marker);
    }

    public function test_a_baseline_revision_renders_as_a_dedicated_row(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->revisionFor($act, [
            'user_id' => $user->id,
            'origin' => RevisionOrigin::Baseline,
            'label' => null,
        ]);

        $response = $this->actingAs($user)->get(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description'])
        );

        $response->assertOk();
        $response->assertSee('Baseline — value before revision history');
    }

    public function test_field_switcher_links_to_sibling_fields_of_a_multi_field_entity(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $response = $this->actingAs($user)->get(
            route('revisions.index', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'description'])
        );

        $response->assertOk();
        $response->assertSee(route('revisions.index', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'notes']), false);
        $response->assertSee(route('revisions.index', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']), false);
    }

    public function test_a_non_owner_gets_403_on_the_history_index(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = $this->actFor($owner);

        $this->revisionFor($act, ['user_id' => $owner->id]);

        $this->actingAs($other)->get(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description'])
        )->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Compare
    // ---------------------------------------------------------------------

    public function test_compare_between_two_revisions_with_a_real_prose_change_shows_the_diff(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $from = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>The cat sat.</p>',
            'created_at' => now()->subMinutes(10),
        ]);
        $to = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>The cat sat quietly.</p>',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('revisions.compare', [
            'entity' => 'act', 'id' => $act->id, 'field' => 'description', 'from' => $from->id, 'to' => $to->id,
        ]));

        $response->assertOk();
        $response->assertSee('quietly');
        $response->assertDontSee('Formatting changed only.');
    }

    public function test_compare_between_two_revisions_that_only_differ_in_markup_shows_formatting_changed_only(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $from = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>Hello world</p>',
            'created_at' => now()->subMinutes(10),
        ]);
        $to = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<div>Hello world</div>',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('revisions.compare', [
            'entity' => 'act', 'id' => $act->id, 'field' => 'description', 'from' => $from->id, 'to' => $to->id,
        ]));

        $response->assertOk();
        $response->assertSee('Formatting changed only.');
    }

    public function test_compare_on_a_markdown_field_diffs_the_raw_text_directly(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $from = Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->project->id,
            'field' => 'contents',
            'user_id' => $user->id,
            'value' => 'Hello world',
            'created_at' => now()->subMinutes(10),
        ]);
        $to = Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->project->id,
            'field' => 'contents',
            'user_id' => $user->id,
            'value' => 'Hello **world**',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('revisions.compare', [
            'entity' => 'scene', 'id' => $scene->id, 'field' => 'contents', 'from' => $from->id, 'to' => $to->id,
        ]));

        $response->assertOk();
        // The raw markdown asterisks must be part of the diff (not stripped like a
        // rich field's HTML) — this field's markup IS the content. Each `**` run
        // is its own inserted diff segment, so assert on the pair of them rather
        // than one contiguous "**world**" string.
        $this->assertSame(2, substr_count($response->getContent(), '<ins>**</ins>'));
        $response->assertDontSee('Formatting changed only.');
    }

    public function test_compare_defaults_to_the_two_most_recent_revisions_when_no_pair_is_given(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>Old text</p>',
            'created_at' => now()->subMinutes(10),
        ]);
        $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>New text arrives</p>',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(
            route('revisions.compare', ['entity' => 'act', 'id' => $act->id, 'field' => 'description'])
        );

        $response->assertOk();
        $response->assertSee('arrives');
    }

    public function test_a_non_owner_gets_403_on_compare(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = $this->actFor($owner);

        $from = $this->revisionFor($act, ['user_id' => $owner->id, 'created_at' => now()->subMinutes(10)]);
        $to = $this->revisionFor($act, ['user_id' => $owner->id, 'created_at' => now()]);

        $this->actingAs($other)->get(route('revisions.compare', [
            'entity' => 'act', 'id' => $act->id, 'field' => 'description', 'from' => $from->id, 'to' => $to->id,
        ]))->assertForbidden();
    }
}
