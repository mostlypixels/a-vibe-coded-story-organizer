<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Events\SceneContentsChanged;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The HTTP surface of the autosave-with-revisions feature:
 * FieldAutosaveController::update(), its routes, the base_hash conflict check,
 * and the coarse-trigger SceneReferenceMatcher/SceneContentsChanged wiring.
 */
class FieldAutosaveTest extends TestCase
{
    use RefreshDatabase;

    /** Build a full project -> act chain owned by the given user. */
    private function actFor(User $user): Act
    {
        [$project, $book] = $this->projectWithBook($user);

        return Act::factory()->for($book)->create();
    }

    /** Build a full project -> act -> chapter -> scene chain owned by the given user. */
    private function sceneFor(User $user, array $overrides = []): Scene
    {
        $act = $this->actFor($user);
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create($overrides);
    }

    private function hashOf(?string $value): string
    {
        return hash('sha256', $value ?? '');
    }

    // ---------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------

    /** Return the hash of the sanitized stored value. */
    public function test_the_response_reports_the_sanitized_value_so_the_next_autosave_does_not_conflict(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $dirty = '<p onclick="steal()">Kept</p><script>bad()</script>';

        $response = $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => $dirty, 'base_hash' => $this->hashOf($act->description)],
        )->assertOk();

        $stored = (string) $act->fresh()->description;

        $this->assertNotSame($dirty, $stored, 'the sanitizer must have changed the value, or this test proves nothing');
        $this->assertSame($stored, $response->json('value'));
        $this->assertSame($this->hashOf($stored), $response->json('hash'));

        // The recorded revision agrees with the column, so later diffs are honest.
        $this->assertSame($stored, Revision::where('field', 'description')->latest('id')->first()->value);

        // The second autosave, hashing what the first reported, must not 409.
        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => '<p>Second</p>', 'base_hash' => $response->json('hash')],
        )->assertOk();
    }

    /**
     * `revision_id` is reported from the row `record()` returned rather than
     * re-queried. The no-op branch — same text sent twice — still has to look it
     * up, because nothing was recorded and the client should still learn which
     * revision its text corresponds to.
     */
    public function test_the_response_names_the_revision_it_wrote_and_the_previous_one_on_a_no_op(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $written = $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => '<p>One</p>', 'base_hash' => $this->hashOf($act->description)],
        )->assertOk();

        $revision = Revision::where('field', 'description')
            ->where('origin', RevisionOrigin::Automatic)
            ->latest('id')
            ->first();

        $this->assertSame($revision->id, $written->json('revision_id'));

        // Byte-identical resend: nothing is recorded, and the id still points at
        // the revision that holds this text.
        $noOp = $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => '<p>One</p>', 'base_hash' => $written->json('hash')],
        )->assertOk();

        $this->assertSame($revision->id, $noOp->json('revision_id'));
        $this->assertSame(1, Revision::where('field', 'description')->where('origin', RevisionOrigin::Automatic)->count());
    }

    public function test_autosaving_a_rich_field_updates_the_column_and_creates_one_revision(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $response = $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => '<p>New description</p>', 'base_hash' => $this->hashOf($act->description)],
        );

        $response->assertOk();
        $act->refresh();
        $this->assertSame('<p>New description</p>', $act->description);
        // ensureBaseline() seeds one row for the pre-edit value the first time this
        // (entity, field) pair is ever recorded, plus the new automatic revision
        // this save itself writes — two rows total, not one.
        $this->assertSame(2, Revision::where('field', 'description')
            ->where('revisionable_id', $act->id)
            ->where('revisionable_type', Act::class)
            ->count());
        $this->assertSame(1, Revision::where('field', 'description')
            ->where('revisionable_id', $act->id)
            ->where('revisionable_type', Act::class)
            ->where('origin', RevisionOrigin::Automatic)
            ->where('value', '<p>New description</p>')
            ->count());
    }

    public function test_autosaving_a_markdown_field_updates_the_column_and_creates_one_revision(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Old contents']);

        $response = $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']),
            ['value' => 'New **contents**', 'base_hash' => $this->hashOf('Old contents')],
        );

        $response->assertOk();
        $scene->refresh();
        $this->assertSame('New **contents**', $scene->contents);
        // Same baseline-plus-new-write shape as the rich-field case above.
        $this->assertSame(2, Revision::where('field', 'contents')
            ->where('revisionable_id', $scene->id)
            ->where('revisionable_type', Scene::class)
            ->count());
        $this->assertSame(1, Revision::where('field', 'contents')
            ->where('revisionable_id', $scene->id)
            ->where('revisionable_type', Scene::class)
            ->where('origin', RevisionOrigin::Automatic)
            ->where('value', 'New **contents**')
            ->count());
    }

    /**
     * The baseline must hold the text the writer started from. Seeding it after
     * the save recorded the *new* text as the initial value, so the first edit
     * of a session was invisible in every later diff — and unrecoverable, since
     * the pre-edit text was then stored nowhere.
     */
    public function test_the_baseline_holds_the_text_from_before_the_first_autosave(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Original contents']);
        $updatedAt = $scene->updated_at;

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']),
            ['value' => 'Original contents plus an edit', 'base_hash' => $this->hashOf('Original contents')],
        )->assertOk();

        $baseline = $scene->revisions()->where('field', 'contents')->where('origin', RevisionOrigin::Baseline)->sole();

        $this->assertSame('Original contents', $baseline->value);
        $this->assertTrue($baseline->created_at->equalTo($updatedAt));
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_a_non_owner_cannot_autosave_a_field(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = $this->actFor($owner);

        $this->actingAs($other)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => 'Hijacked', 'base_hash' => $this->hashOf($act->description)],
        )->assertForbidden();

        $this->assertSame($act->description, $act->fresh()->description);
    }

    // ---------------------------------------------------------------------
    // Registry as a router-level security boundary
    // ---------------------------------------------------------------------

    public function test_an_unregistered_entity_slug_404s_at_the_router(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson(
            '/autosave/not-a-real-slug/1/description',
            ['value' => 'x', 'base_hash' => $this->hashOf(null)],
        )->assertNotFound();
    }

    public function test_a_field_not_registered_for_the_slug_404s(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        // `notes` is a registered field on Scene but not on Act — the shared
        // AutosavableFields::resolveField() 404s it before any write happens.
        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'notes']),
            ['value' => 'x', 'base_hash' => $this->hashOf(null)],
        )->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    public function test_validation_failure_returns_422_using_the_registrys_rule(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        // book.rights has a 1_000 character cap (config/revisions.php).
        $tooLong = str_repeat('a', 1_001);

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'book', 'id' => $book->id, 'field' => 'rights']),
            ['value' => $tooLong, 'base_hash' => $this->hashOf($book->rights)],
        )->assertStatus(422)->assertJsonValidationErrors(['value']);
    }

    // ---------------------------------------------------------------------
    // base_hash conflict
    // ---------------------------------------------------------------------

    public function test_a_correct_base_hash_saves_successfully(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => 'Fresh text', 'base_hash' => $this->hashOf($act->description)],
        )->assertOk();
    }

    public function test_a_stale_base_hash_returns_409_and_leaves_the_column_unchanged(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $originalDescription = $act->description;

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => 'Fresh text', 'base_hash' => 'not-the-real-hash'],
        )->assertStatus(409);

        $this->assertSame($originalDescription, $act->fresh()->description);
    }

    // ---------------------------------------------------------------------
    // Hash authority — response hash reflects the stored (post-mutator) value
    // ---------------------------------------------------------------------

    public function test_the_response_hash_matches_the_sanitized_stored_value_not_the_sent_value(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        // A <script> tag survives the request payload but never survives
        // HtmlSanitizer's clean() — the sent and stored values will differ.
        $sent = '<p>Hello</p><script>alert(1)</script>';

        $response = $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => $sent, 'base_hash' => $this->hashOf($act->description)],
        )->assertOk();

        $storedValue = $act->fresh()->description;
        $this->assertNotSame($sent, $storedValue, 'the sanitizer should have altered the sent value');
        $this->assertSame(hash('sha256', $storedValue), $response->json('hash'));
    }

    // ---------------------------------------------------------------------
    // Byte-identical no-op
    // ---------------------------------------------------------------------

    public function test_a_byte_identical_automatic_save_writes_no_new_revision(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $description = $act->description;

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => $description, 'base_hash' => $this->hashOf($description)],
        )->assertOk();

        $this->assertSame(0, Revision::where('field', 'description')
            ->where('revisionable_id', $act->id)
            ->where('revisionable_type', Act::class)
            ->count());
    }

    // ---------------------------------------------------------------------
    // Coarse trigger: SceneReferenceMatcher
    // ---------------------------------------------------------------------

    public function test_run_matcher_true_on_scene_contents_updates_scene_codex_entry(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Nothing interesting yet.']);
        $project = $scene->chapter->act->book->project;
        CodexEntry::factory()->for($project)->create(['name' => 'Melchior']);

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']),
            [
                'value' => 'Melchior arrives at dawn.',
                'base_hash' => $this->hashOf($scene->contents),
                'run_matcher' => true,
            ],
        )->assertOk();

        $this->assertTrue($scene->codexReferences()->where('name', 'Melchior')->exists());
    }

    public function test_a_bare_debounce_tick_does_not_run_the_matcher(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Nothing interesting yet.']);
        $project = $scene->chapter->act->book->project;
        CodexEntry::factory()->for($project)->create(['name' => 'Melchior']);

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']),
            ['value' => 'Melchior arrives at dawn.', 'base_hash' => $this->hashOf($scene->contents)],
        )->assertOk();

        $this->assertFalse($scene->codexReferences()->where('name', 'Melchior')->exists());
    }

    public function test_run_matcher_true_on_scene_contents_fires_scene_contents_changed(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Old.']);

        // Faked after the fixture: Event::fake() also silences the model events
        // that give a new project its first book.
        Event::fake();

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']),
            ['value' => 'New.', 'base_hash' => $this->hashOf('Old.'), 'run_matcher' => true],
        )->assertOk();

        Event::assertDispatched(SceneContentsChanged::class, fn ($event) => $event->scene->is($scene));
    }

    public function test_a_bare_debounce_tick_does_not_fire_scene_contents_changed(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Old.']);

        // Faked after the fixture: Event::fake() also silences the model events
        // that give a new project its first book.
        Event::fake();

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']),
            ['value' => 'New.', 'base_hash' => $this->hashOf('Old.')],
        )->assertOk();

        Event::assertNotDispatched(SceneContentsChanged::class);
    }

    // ---------------------------------------------------------------------
    // Word count
    // ---------------------------------------------------------------------

    /**
     * Scene.contents is the one field with a stored `word_count` column, kept
     * true to the saved value by Scene::booted()'s `saving` hook. This asserts
     * the response reads that column *after* save, and pins the exact count so
     * a read of stale state (the pre-save value) fails the test.
     */
    public function test_the_response_reports_the_stored_word_count_for_scene_contents(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Old contents here.']);

        $response = $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']),
            ['value' => 'New scene contents with five words.', 'base_hash' => $this->hashOf('Old contents here.')],
        )->assertOk();

        // New, scene, contents, with, five, words. — 6 words.
        $this->assertSame(6, $scene->fresh()->word_count);
        $this->assertSame(6, $response->json('word_count'));
    }

    /** Return the server count when the editor includes fenced code words. */
    public function test_a_fenced_code_block_is_excluded_from_the_response_word_count(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Old contents here.']);

        $withFence = "Real prose here.\n\n```\none two three four five\n```\n\nMore prose after.";

        $response = $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']),
            ['value' => $withFence, 'base_hash' => $this->hashOf('Old contents here.')],
        )->assertOk();

        // Real, prose, here., More, prose, after. — the fenced 5 words are excluded.
        $this->assertSame(6, $scene->fresh()->word_count);
        $this->assertSame(6, $response->json('word_count'));
    }

    /**
     * Only `scenes.contents` has a stored column (word-count spec's binding
     * decision). Every other autosaved field — an act's Rich `description` here
     * — has nothing to read back, so the response counts it on the fly from the
     * value that was actually persisted.
     */
    public function test_a_non_scene_field_also_returns_a_word_count(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $response = $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => '<p>Hello brave new world</p>', 'base_hash' => $this->hashOf($act->description)],
        )->assertOk();

        // Hello, brave, new, world — 4 words, tags stripped before counting.
        $this->assertSame(4, $response->json('word_count'));
    }

    /**
     * `word_count` is an addition to the response; it must not reshape the rest
     * of the payload.
     */
    public function test_word_count_is_added_alongside_the_existing_response_keys(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $response = $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => '<p>Fresh text</p>', 'base_hash' => $this->hashOf($act->description)],
        )->assertOk();

        $response->assertJsonStructure(['value', 'hash', 'word_count', 'revision_id', 'saved_at']);
    }

    // ---------------------------------------------------------------------
    // Rate limiting
    // ---------------------------------------------------------------------

    public function test_exceeding_the_throttle_returns_429_with_retry_after(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $response = null;

        for ($i = 0; $i < 121; $i++) {
            $response = $this->actingAs($user)->patchJson(
                route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
                ['value' => "Attempt {$i}", 'base_hash' => $this->hashOf($act->fresh()->description)],
            );
        }

        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }
}
