<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task 14 — the entity compare page: two save points, every field that differs.
 *
 * The property most of these tests exist to pin is that comparison is about two
 * *moments*, not two edits. A save that touched only the dedication still
 * implies a state for the description, so a field neither chosen save wrote can
 * legitimately appear as changed — and a field both moments agree on never
 * appears at all.
 */
class RevisionCompareTest extends TestCase
{
    use RefreshDatabase;

    private function actFor(User $user): Act
    {
        return Act::factory()->for(Project::factory()->for($user)->create())->create();
    }

    private function sceneFor(User $user): Scene
    {
        return Scene::factory()->for(Chapter::factory()->for($this->actFor($user))->create())->create();
    }

    /**
     * One revision in a save point of its own, unless `save_id` is passed.
     */
    private function revisionFor(Act $act, array $overrides = []): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => Act::class,
            'revisionable_id' => $act->id,
            'project_id' => $act->project->id,
            'field' => 'description',
            'save_id' => (string) Str::ulid(),
            ...$overrides,
        ]);
    }

    /**
     * One revision of a Scene field, in a save point of its own.
     *
     * A Scene is the entity used wherever a test needs *several* registered
     * fields (description, notes, contents); an Act registers only one.
     */
    private function sceneRevision(Scene $scene, string $field, ?string $value, $at): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->project->id,
            'field' => $field,
            'save_id' => (string) Str::ulid(),
            'user_id' => $scene->chapter->act->project->user_id,
            'value' => $value,
            'created_at' => $at,
        ]);
    }

    private function compareUrl(Act $act, array $query = []): string
    {
        return route('revisions.compare', ['entity' => 'act', 'id' => $act->id, ...$query]);
    }

    private function sceneCompareUrl(Scene $scene, array $query = []): string
    {
        return route('revisions.compare', ['entity' => 'scene', 'id' => $scene->id, ...$query]);
    }

    public function test_the_owner_can_compare_two_save_points(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $from = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>The ferry left.</p>',
            'created_at' => now()->subDay(),
        ]);
        $to = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>The ferry slipped away.</p>',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->compareUrl($act, [
            'from' => $from->save_id, 'to' => $to->save_id,
        ]));

        $response->assertOk();
        // The section names the whole comparison, not just the field — a bare
        // "Description" left the reader to work out what they were looking at.
        $response->assertSee("Comparing changes to Act field 'Description'");
        $response->assertSee('What changed');
        $response->assertSee('slipped away');
    }

    public function test_a_non_owner_gets_403(): void
    {
        $owner = User::factory()->create();
        $act = $this->actFor($owner);
        $this->revisionFor($act, ['user_id' => $owner->id]);

        $this->actingAs(User::factory()->create())
            ->get($this->compareUrl($act))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $act = $this->actFor(User::factory()->create());

        $this->get($this->compareUrl($act))->assertRedirect(route('login'));
    }

    public function test_an_unknown_save_id_404s(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $from = $this->revisionFor($act, ['user_id' => $user->id, 'created_at' => now()->subDay()]);
        $this->revisionFor($act, ['user_id' => $user->id, 'created_at' => now()]);

        $this->actingAs($user)
            ->get($this->compareUrl($act, ['from' => $from->save_id, 'to' => (string) Str::ulid()]))
            ->assertNotFound();
    }

    public function test_a_reversed_pair_renders_the_same_diff_as_the_correct_order(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $older = $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>Old text</p>', 'created_at' => now()->subDay()]);
        $newer = $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>New text</p>', 'created_at' => now()]);

        // Direction is not the reader's to get wrong: a backwards pair is put
        // back in order rather than rejected or diffed in reverse.
        $forwards = $this->actingAs($user)->get($this->compareUrl($act, ['from' => $older->save_id, 'to' => $newer->save_id]));
        $backwards = $this->actingAs($user)->get($this->compareUrl($act, ['from' => $newer->save_id, 'to' => $older->save_id]));

        $forwards->assertOk();
        $backwards->assertOk();

        foreach ([$forwards, $backwards] as $response) {
            $this->assertStringContainsString('<ins', $response->getContent());
            $this->assertStringContainsString('New', $response->getContent());
        }
    }

    public function test_no_pair_defaults_to_the_two_most_recent_save_points(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>Oldest</p>', 'created_at' => now()->subDays(3)]);
        $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>Middle</p>', 'created_at' => now()->subDay()]);
        $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>Newest</p>', 'created_at' => now()]);

        $response = $this->actingAs($user)->get($this->compareUrl($act));

        $response->assertOk();
        // "What changed last" is what a reader lands here to ask.
        $response->assertSee('Newest');
        $response->assertSee('Middle');
        $response->assertDontSee('Oldest');
    }

    public function test_fewer_than_two_save_points_shows_the_empty_state(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $this->revisionFor($act, ['user_id' => $user->id]);

        $this->actingAs($user)
            ->get($this->compareUrl($act))
            ->assertOk()
            ->assertSee('Nothing to compare yet.');
    }

    public function test_a_field_neither_save_wrote_appears_when_it_changed_in_between(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        // The two chosen points both touched the description. A save between
        // them changed the notes — comparing two *states* has to report that.
        $from = $this->sceneRevision($scene, 'description', '<p>Old</p>', now()->subDays(3));
        $this->sceneRevision($scene, 'notes', '<p>A note appeared.</p>', now()->subDays(2));
        $to = $this->sceneRevision($scene, 'description', '<p>New</p>', now());

        $response = $this->actingAs($user)->get($this->sceneCompareUrl($scene, [
            'from' => $from->save_id, 'to' => $to->save_id,
        ]));

        $response->assertOk();
        $response->assertSee('Notes');
        $response->assertSee('A note appeared.');
    }

    public function test_unchanged_fields_are_reported_as_a_count_not_as_sections(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $from = $this->sceneRevision($scene, 'description', '<p>Old</p>', now()->subDay());
        $to = $this->sceneRevision($scene, 'description', '<p>New</p>', now());

        $response = $this->actingAs($user)->get($this->sceneCompareUrl($scene, [
            'from' => $from->save_id, 'to' => $to->save_id,
        ]));

        // A Scene registers description, notes and contents; only the first
        // changed, and the other two collapse into one muted line rather than
        // two empty sections.
        $response->assertOk();
        $response->assertSee('2 other fields unchanged (Notes, Contents)');
    }

    public function test_a_field_that_did_not_exist_at_the_older_point_reads_as_new(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $from = $this->sceneRevision($scene, 'description', '<p>Something</p>', now()->subDay());
        $to = $this->sceneRevision($scene, 'notes', '<p>A brand new note.</p>', now());

        $response = $this->actingAs($user)->get($this->sceneCompareUrl($scene, [
            'from' => $from->save_id, 'to' => $to->save_id,
        ]));

        $response->assertOk();
        $response->assertSee('A brand new note.');
        $response->assertSee('New');
    }

    public function test_the_field_filter_renders_exactly_one_section(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $from = $this->sceneRevision($scene, 'description', '<p>Old</p>', now()->subDays(2));
        $this->sceneRevision($scene, 'notes', '<p>Noted.</p>', now()->subDay());
        $to = $this->sceneRevision($scene, 'description', '<p>New</p>', now());

        $response = $this->actingAs($user)->get($this->sceneCompareUrl($scene, [
            'from' => $from->save_id, 'to' => $to->save_id, 'field' => 'description',
        ]));

        $response->assertOk();
        $response->assertSee('Show all fields');
        $response->assertDontSee('Noted.');
        $this->assertSame(1, substr_count($response->getContent(), 'aria-labelledby="diff-'));
    }

    public function test_an_unregistered_field_filter_404s(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $this->revisionFor($act, ['user_id' => $user->id]);

        $this->actingAs($user)
            ->get($this->compareUrl($act, ['field' => 'not_a_field']))
            ->assertNotFound();
    }

    public function test_the_newer_picker_disables_every_option_not_newer_than_the_older_one(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $oldest = $this->revisionFor($act, ['user_id' => $user->id, 'created_at' => now()->subDays(3)]);
        $middle = $this->revisionFor($act, ['user_id' => $user->id, 'created_at' => now()->subDays(2)]);
        $newest = $this->revisionFor($act, ['user_id' => $user->id, 'created_at' => now()]);

        $response = $this->actingAs($user)->get($this->compareUrl($act, [
            'from' => $middle->save_id, 'to' => $newest->save_id,
        ]));

        $response->assertOk();

        // The invalid pairing is made unreachable rather than accepted and then
        // rejected — there is no backwards diff and no error state to design.
        // Asserted on the *baseline* <select>, since that is what has to hold
        // with JS off; the combobox reads the same server-decided flags.
        $newerSelect = substr($response->getContent(), (int) strpos($response->getContent(), 'name="to"'));

        $this->assertTrue($this->optionIsDisabled($newerSelect, $oldest->save_id), 'an older save was still selectable as the newer side');
        $this->assertTrue($this->optionIsDisabled($newerSelect, $middle->save_id), 'the older side itself was still selectable as the newer side');
        $this->assertFalse($this->optionIsDisabled($newerSelect, $newest->save_id), 'a genuinely newer save was locked out');
    }

    /**
     * Whether the `<option>` carrying `$saveId` is disabled, matched on the
     * opening tag so the assertion does not depend on Blade's indentation.
     */
    private function optionIsDisabled(string $html, string $saveId): bool
    {
        preg_match('/<option\b[^>]*value="'.preg_quote($saveId, '/').'"[^>]*>/s', $html, $matches);

        $this->assertNotEmpty($matches, "No <option> found for save {$saveId}.");

        return str_contains($matches[0], 'disabled');
    }

    public function test_the_pickers_carry_their_aria_contract_server_side(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->revisionFor($act, ['user_id' => $user->id, 'created_at' => now()->subDay()]);
        $this->revisionFor($act, ['user_id' => $user->id, 'created_at' => now()]);

        $response = $this->actingAs($user)->get($this->compareUrl($act));

        // The combobox is an enhancement, but its roles and labelling are
        // rendered by the server — so the DOM is already correct before Alpine
        // touches it, and a bundle that fails to load cannot leave behind a
        // half-built widget that lies to a screen reader.
        $response->assertOk();
        $response->assertSee('role="combobox"', escape: false);
        $response->assertSee('role="listbox"', escape: false);
        $response->assertSee('role="option"', escape: false);
        $response->assertSee('aria-controls="from-listbox"', escape: false);
        $response->assertSee('aria-controls="to-listbox"', escape: false);
        $response->assertSee('aria-labelledby="from-label"', escape: false);
        $response->assertSee('aria-labelledby="to-label"', escape: false);

        // …and the no-JS baseline is still a real <select> with a real name.
        $response->assertSee('<select', escape: false);
        $response->assertSee('name="from"', escape: false);
        $response->assertSee('name="to"', escape: false);
    }

    public function test_the_legacy_field_scoped_url_redirects_to_the_equivalent_save_points(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $from = $this->revisionFor($act, ['user_id' => $user->id, 'created_at' => now()->subDay()]);
        $to = $this->revisionFor($act, ['user_id' => $user->id, 'created_at' => now()]);

        // The old URL carried *revision* ids; a revision knows its save point,
        // so a bookmark still lands on the comparison it was about.
        $this->actingAs($user)
            ->get(route('revisions.field-compare', [
                'entity' => 'act', 'id' => $act->id, 'field' => 'description',
                'from' => $from->id, 'to' => $to->id,
            ]))
            ->assertRedirect($this->compareUrl($act, [
                'field' => 'description', 'from' => $from->save_id, 'to' => $to->save_id,
            ]));
    }

    public function test_the_legacy_url_survives_a_pair_that_no_longer_exists(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $this->revisionFor($act, ['user_id' => $user->id]);

        // A pruned revision loses the bookmark its pair, not its page.
        $this->actingAs($user)
            ->get(route('revisions.field-compare', [
                'entity' => 'act', 'id' => $act->id, 'field' => 'description',
                'from' => 999999, 'to' => 999998,
            ]))
            ->assertRedirect($this->compareUrl($act, ['field' => 'description']));
    }

    public function test_a_markdown_field_still_compares_its_raw_text(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $make = fn (string $value, $at) => Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->project->id,
            'field' => 'contents',
            'save_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'value' => $value,
            'created_at' => $at,
        ]);

        $from = $make('Hello world', now()->subDay());
        $to = $make('Hello **world**', now());

        $response = $this->actingAs($user)->get(route('revisions.compare', [
            'entity' => 'scene', 'id' => $scene->id, 'from' => $from->save_id, 'to' => $to->save_id,
        ]));

        $response->assertOk();
        // The asterisks are the content here, so they have to survive.
        $this->assertSame(2, substr_count($response->getContent(), '<ins>**</ins>'));
    }

    public function test_a_formatting_only_change_renders_its_badge_end_to_end(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        // The proof that the whole visual-diff chain works through the page:
        // tokenizer → differ → renderer → x-diff, for a save that changed no
        // words at all.
        $from = $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>Hello world</p>', 'created_at' => now()->subDay()]);
        $to = $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>Hello <strong>world</strong></p>', 'created_at' => now()]);

        $response = $this->actingAs($user)->get($this->compareUrl($act, [
            'from' => $from->save_id, 'to' => $to->save_id,
        ]));

        $response->assertOk();
        $response->assertSee('formatting changed: bold added');
        $response->assertSee('<strong>world</strong>', escape: false);
    }

    public function test_each_side_renders_its_whole_value_with_its_own_revert_button(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        // Neither compared point is the current one, so both sides are
        // restorable — the case where the two buttons have to be told apart.
        $older = $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>The ferry left at dawn.</p>', 'created_at' => now()->subDays(3)]);
        $newer = $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>The ferry slipped away at dawn.</p>', 'created_at' => now()->subDays(2)]);
        $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>Something later entirely.</p>', 'created_at' => now()]);

        $response = $this->actingAs($user)->get($this->compareUrl($act, [
            'from' => $older->save_id, 'to' => $newer->save_id,
        ]));

        $response->assertOk();

        // The diff shows only what moved; the columns show both versions whole,
        // unchanged opening included.
        $response->assertSee('The ferry left at dawn.');
        $response->assertSee('The ferry slipped away at dawn.');

        // One revert form per column, each pointed at its own revision.
        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, route('revisions.revert', $older)));
        $this->assertSame(1, substr_count($content, route('revisions.revert', $newer)));
    }

    public function test_the_side_that_is_already_the_current_value_offers_no_revert(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $older = $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>Old</p>', 'created_at' => now()->subDay()]);
        $current = $this->revisionFor($act, ['user_id' => $user->id, 'value' => '<p>New</p>', 'created_at' => now()]);

        $response = $this->actingAs($user)->get($this->compareUrl($act, [
            'from' => $older->save_id, 'to' => $current->save_id,
        ]));

        $response->assertOk();

        // Restoring the value the field already holds is a no-op dressed up as
        // an action: the column says what it is instead.
        $response->assertSee('Current version');
        $this->assertSame(0, substr_count($response->getContent(), route('revisions.revert', $current)));
        $this->assertSame(1, substr_count($response->getContent(), route('revisions.revert', $older)));
    }

    public function test_a_field_that_did_not_exist_yet_says_so_instead_of_offering_a_revert(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        // The notes start existing between the two points, so the older column
        // has no revision to show and nothing to restore.
        $from = $this->sceneRevision($scene, 'description', '<p>Unchanged.</p>', now()->subDays(2));
        $this->sceneRevision($scene, 'description', '<p>Changed.</p>', now()->subDay());
        $to = $this->sceneRevision($scene, 'notes', '<p>A brand new note.</p>', now());

        $response = $this->actingAs($user)->get($this->sceneCompareUrl($scene, [
            'from' => $from->save_id, 'to' => $to->save_id, 'field' => 'notes',
        ]));

        $response->assertOk();
        $response->assertSee('This field had no content yet.');
        $response->assertSee('A brand new note.');
    }

    public function test_a_markdown_column_renders_the_value_the_way_the_app_does(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $from = $this->sceneRevision($scene, 'contents', 'Hello world', now()->subDay());
        $to = $this->sceneRevision($scene, 'contents', 'Hello **world**', now());

        $response = $this->actingAs($user)->get($this->sceneCompareUrl($scene, [
            'from' => $from->save_id, 'to' => $to->save_id, 'field' => 'contents',
        ]));

        $response->assertOk();

        // The diff above compares the raw source (the asterisks are the change);
        // the column shows what the writer would get back.
        $response->assertSee('<ins>**</ins>', escape: false);
        $response->assertSee('<strong>world</strong>', escape: false);
    }
}
