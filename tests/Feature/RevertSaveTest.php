<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\View\Components\RevisionsLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Undo this save": every field one save point touched goes back to the value
 * it held *before* that save, in one action.
 *
 * The promise is narrow on purpose: **only** the fields that
 * save touched. It is never a whole-entity rollback to that moment, which would
 * silently discard unrelated later edits to other fields — the third test below
 * is what pins that.
 *
 * The scene these tests build has three save points, oldest first:
 *
 *   A — description "<p>D1</p>", notes "<p>N1</p>"
 *   B — description "<p>D2</p>", notes "<p>N2</p>"   ← the one being undone
 *   C — contents "C1"                                ← newest, so B is not current
 *
 * Undoing B must restore D1/N1 and leave C1 alone.
 */
class RevertSaveTest extends TestCase
{
    use RefreshDatabase;

    private string $saveA;

    private string $saveB;

    private string $saveC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->saveA = (string) Str::ulid();
        $this->saveB = (string) Str::ulid();
        $this->saveC = (string) Str::ulid();
    }

    private function sceneFor(User $user): Scene
    {
        $act = Act::factory()->for(Project::factory()->for($user)->create())->create();

        return Scene::factory()->for(Chapter::factory()->for($act)->create())->create([
            // The live state: what save B and save C left behind.
            'description' => '<p>D2</p>',
            'notes' => '<p>N2</p>',
            'contents' => 'C1',
        ]);
    }

    private function revision(Scene $scene, string $saveId, string $field, string $value, int $minutesAgo): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->project->id,
            'user_id' => $scene->chapter->act->project->user_id,
            'save_id' => $saveId,
            'field' => $field,
            'value' => $value,
            'origin' => RevisionOrigin::Manual,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    /**
     * The three save points above, in order.
     */
    private function withHistory(Scene $scene): Scene
    {
        $this->revision($scene, $this->saveA, 'description', '<p>D1</p>', 30);
        $this->revision($scene, $this->saveA, 'notes', '<p>N1</p>', 30);
        $this->revision($scene, $this->saveB, 'description', '<p>D2</p>', 20);
        $this->revision($scene, $this->saveB, 'notes', '<p>N2</p>', 20);
        $this->revision($scene, $this->saveC, 'contents', 'C1', 10);

        return $scene;
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function hashesFor(Scene $scene, array $overrides = []): array
    {
        $hashes = [];

        foreach (['description', 'notes', 'contents'] as $field) {
            $hashes[$field] = hash('sha256', (string) ($scene->getAttribute($field) ?? ''));
        }

        return [...$hashes, ...$overrides];
    }

    private function undo(User $user, string $saveId, array $hashes)
    {
        return $this->actingAs($user)->post(route('revisions.saves.revert', $saveId), [
            'base_hashes' => $hashes,
        ]);
    }

    public function test_undoing_a_save_restores_every_field_it_touched(): void
    {
        $user = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($user));

        $this->undo($user, $this->saveB, $this->hashesFor($scene))->assertRedirect();

        $scene->refresh();
        $this->assertSame('<p>D1</p>', $scene->description);
        $this->assertSame('<p>N1</p>', $scene->notes);
    }

    public function test_the_undo_is_recorded_as_one_new_save_point_of_revert_rows(): void
    {
        $user = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($user));

        $this->undo($user, $this->saveB, $this->hashesFor($scene));

        $reverts = Revision::query()->where('origin', RevisionOrigin::Revert)->get();

        $this->assertCount(2, $reverts);
        $this->assertCount(1, $reverts->pluck('save_id')->unique(), 'the undo must be ONE save point');
        $this->assertNotContains($reverts->first()->save_id, [$this->saveA, $this->saveB, $this->saveC]);
        $this->assertEqualsCanonicalizing(['description', 'notes'], $reverts->pluck('field')->all());

        // Additive: the rows it undid are still there, untouched.
        $this->assertSame(2, Revision::query()->where('save_id', $this->saveB)->count());
    }

    public function test_a_field_the_save_did_not_touch_is_left_alone(): void
    {
        $user = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($user));

        $this->undo($user, $this->saveB, $this->hashesFor($scene));

        $scene->refresh();
        $this->assertSame('C1', $scene->contents, 'undo must not roll the whole entity back');
        $this->assertSame(
            0,
            Revision::query()->where('field', 'contents')->where('origin', RevisionOrigin::Revert)->count(),
        );
    }

    public function test_it_redirects_to_the_edit_form_with_a_flash_naming_the_restored_fields(): void
    {
        $user = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($user));

        $response = $this->undo($user, $this->saveB, $this->hashesFor($scene));

        $response->assertRedirect(route('scenes.edit', $scene));
        $response->assertSessionHas('status', 'reverted-save');
        $response->assertSessionHas('restored_fields', ['Description', 'Notes']);
    }

    public function test_a_stale_hash_on_any_field_writes_nothing_at_all(): void
    {
        $user = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($user));

        $countBefore = Revision::count();

        // Description's hash is correct; notes' is not. All-or-nothing means
        // description must not be restored either.
        $response = $this->undo($user, $this->saveB, $this->hashesFor($scene, ['notes' => 'stale']));

        $response->assertRedirect();
        $response->assertSessionHas(RevisionsLayout::ERROR_KEY);

        $scene->refresh();
        $this->assertSame('<p>D2</p>', $scene->description);
        $this->assertSame('<p>N2</p>', $scene->notes);
        $this->assertSame($countBefore, Revision::count());
    }

    public function test_a_value_that_no_longer_passes_todays_rules_fails_without_storing(): void
    {
        $user = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($user));

        // The rule tightened after the value was recorded — the exact case
        // re-validating on revert exists to catch.
        config()->set('revisions.caps.default', 2);

        $countBefore = Revision::count();

        $this->undo($user, $this->saveB, $this->hashesFor($scene))->assertRedirect();

        $scene->refresh();
        $this->assertSame('<p>D2</p>', $scene->description);
        $this->assertSame('<p>N2</p>', $scene->notes);
        $this->assertSame($countBefore, Revision::count());
    }

    public function test_undoing_the_undo_moves_forward_again(): void
    {
        $user = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($user));

        $this->undo($user, $this->saveB, $this->hashesFor($scene));

        $scene->refresh();
        $undoSaveId = Revision::query()->where('origin', RevisionOrigin::Revert)->value('save_id');

        // The undo is a save point like any other, so it can be undone in turn —
        // which puts the text back where it was before the first undo.
        $this->undo($user, $undoSaveId, $this->hashesFor($scene))->assertRedirect();

        $scene->refresh();
        $this->assertSame('<p>D2</p>', $scene->description);
        $this->assertSame('<p>N2</p>', $scene->notes);
    }

    /**
     * Undo restores what came *before* a save, so undoing the newest one is
     * "undo what I just saved", not a no-op.
     */
    public function test_the_current_save_point_can_be_undone_like_any_other(): void
    {
        $user = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($user));

        // C is the newest save point: it set contents to "C1", and nothing set
        // contents before it, so undoing it empties the field.
        $this->undo($user, $this->saveC, $this->hashesFor($scene))->assertRedirect();

        $scene->refresh();
        $this->assertSame('', $scene->contents);
        $this->assertSame('<p>D2</p>', $scene->description, 'only the field that save touched moves');
    }

    /**
     * A save that *created* a field's content has nothing before it to restore,
     * so undoing it empties the field rather than skipping it — "goes back to
     * its previous value" where the previous value was nothing. Every registered
     * field is nullable, so that is a legal state.
     */
    public function test_undoing_a_save_that_created_a_field_empties_it(): void
    {
        $user = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($user));

        $this->undo($user, $this->saveC, $this->hashesFor($scene));

        $this->assertSame('', $scene->refresh()->contents);
        $this->assertSame(
            1,
            Revision::query()->where('field', 'contents')->where('origin', RevisionOrigin::Revert)->count(),
        );
    }

    public function test_a_non_owner_gets_403(): void
    {
        $owner = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($owner));

        $this->undo(User::factory()->create(), $this->saveB, $this->hashesFor($scene))->assertForbidden();

        $scene->refresh();
        $this->assertSame('<p>D2</p>', $scene->description);
        $this->assertSame(0, Revision::query()->where('origin', RevisionOrigin::Revert)->count());
    }

    /**
     * Its own test rather than a second request in the one above: `actingAs()`
     * holds for the rest of the test, so a "guest" request made after it would
     * still be authenticated and would assert nothing.
     */
    public function test_a_guest_is_sent_to_login(): void
    {
        $scene = $this->withHistory($this->sceneFor(User::factory()->create()));

        $this->post(route('revisions.saves.revert', $this->saveB), [
            'base_hashes' => $this->hashesFor($scene),
        ])->assertRedirect(route('login'));

        $this->assertSame('<p>D2</p>', $scene->refresh()->description);
        $this->assertSame(0, Revision::query()->where('origin', RevisionOrigin::Revert)->count());
    }

    /**
     * The feature's standing rule — list and whole-save queries never hydrate
     * `revisions.value` — held everywhere except here, where the group was
     * fetched with a bare `get()`. The rows are read for the morph target, the
     * dominant origin and the (created_at, id, field) ordering; the predecessor's
     * value comes from its own query in RevisionReverter. Undoing a save of
     * `Scene.contents` therefore used to read up to a megabyte of longText per
     * row to look at four scalars.
     */
    public function test_the_group_lookup_never_hydrates_the_value_column(): void
    {
        $user = User::factory()->create();
        $scene = $this->withHistory($this->sceneFor($user));

        $lookups = [];
        DB::listen(function ($query) use (&$lookups) {
            // The group fetch only — the predecessor queries the reverter runs
            // are *supposed* to read `value`, and are filtered by field, not by
            // save_id.
            if (str_contains($query->sql, 'from "revisions"') && str_contains($query->sql, '"save_id" =')) {
                $lookups[] = $query->sql;
            }
        });

        $this->undo($user, $this->saveB, $this->hashesFor($scene))->assertRedirect();

        $this->assertNotEmpty($lookups, 'the listener caught nothing — the assertions below would pass vacuously');

        foreach ($lookups as $sql) {
            $this->assertStringNotContainsString(
                'select *',
                $sql,
                "The save-group lookup must name its columns, or the next added column ships unnoticed:\n{$sql}",
            );
            $this->assertStringNotContainsString(
                '"value"',
                $sql,
                "The undo hydrated `value`. It can be megabytes of scene contents:\n{$sql}",
            );
        }
    }

    public function test_an_unknown_save_id_404s_and_a_malformed_one_never_reaches_the_controller(): void
    {
        $user = User::factory()->create();
        $this->withHistory($this->sceneFor($user));

        // Well-formed ULID, no such group.
        $this->actingAs($user)->post(route('revisions.saves.revert', (string) Str::ulid()), [
            'base_hashes' => ['description' => 'x'],
        ])->assertNotFound();

        // Malformed: the route constraint rejects it before any query runs.
        $this->actingAs($user)->post('/revisions/saves/not-a-ulid/revert', [
            'base_hashes' => ['description' => 'x'],
        ])->assertNotFound();
    }
}
