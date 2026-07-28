<?php

namespace Tests\Feature;

use App\Enums\FieldKind;
use App\Enums\SceneStatus;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\Services\Import\ProjectGraphImporter;
use App\Support\WordCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task 4 (word-count spec) — the feature's one invariant:
 * `scenes.word_count` always equals `WordCounter::count($scene->contents,
 * FieldKind::Markdown)` for the stored value, on **every** write path
 * (expanded/architecture.md "The write path", plan/04-scene-saving-hook.md).
 *
 * Held by a `saving` hook in `Scene::booted()`, not by any controller — the
 * whole reason it lives there is that `RevisionReverter` writes through
 * `$entity->save()` and never touches `FieldAutosaveController`, so a
 * controller-level implementation would leave the count stale the moment
 * someone uses Undo. One test per write path below proves that.
 */
class WordCountTest extends TestCase
{
    use RefreshDatabase;

    private function chapterFor(User $user): Chapter
    {
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        return Chapter::factory()->for($act)->create();
    }

    /** Build a full project -> act -> chapter -> scene chain owned by the given user. */
    private function sceneFor(User $user, array $overrides = []): Scene
    {
        return Scene::factory()->for($this->chapterFor($user))->create($overrides);
    }

    private function validPayload(Chapter $chapter, array $overrides = []): array
    {
        return array_merge([
            'chapter_id' => $chapter->id,
            'name' => 'Opening scene',
            'description' => 'A test scene',
            'contents' => 'Some **markdown** contents.',
            'notes' => null,
            'status' => SceneStatus::Draft->value,
        ], $overrides);
    }

    private function hashOf(?string $value): string
    {
        return hash('sha256', $value ?? '');
    }

    private function assertWordCountMatchesContents(Scene $scene): void
    {
        $scene = $scene->fresh();

        $this->assertSame(
            WordCounter::count($scene->contents, FieldKind::Markdown),
            $scene->word_count,
        );
    }

    // ---------------------------------------------------------------------
    // 1. Manual save via SceneController::update()
    // ---------------------------------------------------------------------

    public function test_a_manual_save_keeps_word_count_true_to_the_saved_contents(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'Short.']);

        $this->actingAs($user)->put(
            route('scenes.update', $scene),
            $this->validPayload($chapter, ['contents' => 'A rather longer set of contents to count.']),
        )->assertRedirect();

        $this->assertWordCountMatchesContents($scene);
    }

    // ---------------------------------------------------------------------
    // 2. Autosave PATCH via FieldAutosaveController
    // ---------------------------------------------------------------------

    public function test_an_autosave_patch_keeps_word_count_true_to_the_saved_contents(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Old contents']);

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']),
            ['value' => 'New markdown contents with several words here.', 'base_hash' => $this->hashOf('Old contents')],
        )->assertOk();

        $this->assertWordCountMatchesContents($scene);
    }

    // ---------------------------------------------------------------------
    // 3. Revert a single field via revisions.revert
    // ---------------------------------------------------------------------

    /**
     * @see expanded/architecture.md "The write path" — RevisionReverter writes
     * through $entity->save(), which is exactly the case a controller-level
     * implementation would miss.
     */
    public function test_reverting_a_field_keeps_word_count_true_to_the_restored_contents(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Current contents with several words in it']);

        $old = Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->project->id,
            'user_id' => $user->id,
            'field' => 'contents',
            'value' => 'Two words',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->post(route('revisions.revert', $old), [
            'base_hash' => $this->hashOf($scene->contents),
        ])->assertRedirect();

        $this->assertSame('Two words', $scene->fresh()->contents);
        $this->assertWordCountMatchesContents($scene);
    }

    // ---------------------------------------------------------------------
    // 4. Undo a whole save via revisions.saves.revert
    // ---------------------------------------------------------------------

    /**
     * Same rationale as the single-field revert above: `revertSave()` also
     * writes through `$entity->save()` inside `RevisionReverter::restore()`.
     */
    public function test_undoing_a_save_keeps_word_count_true_to_the_restored_contents(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => 'Alpha beta gamma delta epsilon']);

        $saveA = (string) Str::ulid();
        $saveB = (string) Str::ulid();

        // What the scene held before the save being undone.
        Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->project->id,
            'user_id' => $user->id,
            'save_id' => $saveA,
            'field' => 'contents',
            'value' => 'Uno dos',
            'created_at' => now()->subMinutes(20),
        ]);

        // The save being undone: it wrote the scene's current live value.
        Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->project->id,
            'user_id' => $user->id,
            'save_id' => $saveB,
            'field' => 'contents',
            'value' => 'Alpha beta gamma delta epsilon',
            'created_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($user)->post(route('revisions.saves.revert', $saveB), [
            'base_hashes' => ['contents' => $this->hashOf($scene->contents)],
        ])->assertRedirect();

        $this->assertSame('Uno dos', $scene->fresh()->contents);
        $this->assertWordCountMatchesContents($scene);
    }

    // ---------------------------------------------------------------------
    // 5. Project import via ProjectGraphImporter
    // ---------------------------------------------------------------------

    /**
     * `ProjectGraphImporter::importScene()` creates through
     * `$chapter->scenes()->create()` (confirmed by reading the importer), so
     * the hook fires with no importer change. This pins that rather than
     * assuming it (data-model.md "Import / export").
     */
    public function test_an_imported_scene_has_its_word_count_set(): void
    {
        $fixtureRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'word-count-import-test-'.uniqid();
        $this->writeImportFixture($fixtureRoot);

        try {
            $user = User::factory()->create();
            $importer = app(ProjectGraphImporter::class);

            $project = $importer->importProject($fixtureRoot, $user);
            $idMaps = [];
            $importer->importStory($fixtureRoot, $project, $idMaps);

            $scene = Scene::where('name', 'Imported scene')->firstOrFail();

            $this->assertSame('Some imported prose to count.', $scene->contents);
            $this->assertWordCountMatchesContents($scene);
            $this->assertGreaterThan(0, $scene->word_count);
        } finally {
            $this->deleteImportFixture($fixtureRoot);
        }
    }

    /**
     * A minimal hand-built extraction root: one project, one act, one
     * chapter, one scene with contents — everything ProjectGraphImporter's
     * importProject()/importStory() require and nothing more (mirrors
     * tests/Unit/Import/ProjectGraphImporterTest.php's fixture style).
     */
    private function writeImportFixture(string $fixtureRoot): void
    {
        $this->writeFixtureFile($fixtureRoot, 'data/project/project.json', json_encode([
            'id' => 900,
            'name' => 'Word count import fixture',
        ]));

        $this->writeFixtureFile($fixtureRoot, 'data/acts/100-act-one/act.json', json_encode([
            'id' => 100, 'name' => 'Act One', 'position' => 1,
        ]));
        $this->writeFixtureFile($fixtureRoot, 'data/acts/100-act-one/chapters/200-chapter-one/chapter.json', json_encode([
            'id' => 200, 'name' => 'Chapter One', 'position' => 1,
        ]));
        $this->writeFixtureFile(
            $fixtureRoot,
            'data/acts/100-act-one/chapters/200-chapter-one/scenes/300-scene-one/scene.json',
            json_encode([
                'id' => 300,
                'name' => 'Imported scene',
                'position' => 1,
                'status' => 'draft',
                'event_id' => null,
                'mentioned_event_ids' => [],
                'contents_file' => 'contents.md',
            ]),
        );
        $this->writeFixtureFile(
            $fixtureRoot,
            'data/acts/100-act-one/chapters/200-chapter-one/scenes/300-scene-one/contents.md',
            'Some imported prose to count.',
        );
    }

    private function writeFixtureFile(string $fixtureRoot, string $relativePath, string $contents): void
    {
        $absolute = $fixtureRoot.'/'.$relativePath;

        $directory = dirname($absolute);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolute, $contents);
    }

    private function deleteImportFixture(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->deleteImportFixture($path) : @unlink($path);
        }

        @rmdir($directory);
    }

    // ---------------------------------------------------------------------
    // 6. Scene::factory()->create() — the seeded-scene half lives in
    //    DatabaseSeederTest (data-model.md "Seeding").
    // ---------------------------------------------------------------------

    public function test_a_factory_created_scene_has_its_word_count_set(): void
    {
        $scene = Scene::factory()->create(['contents' => 'A handful of factory words right here.']);

        $this->assertWordCountMatchesContents($scene);
        $this->assertGreaterThan(0, $scene->word_count);
    }

    // ---------------------------------------------------------------------
    // 7. Renaming a scene leaves word_count untouched (the isDirty guard)
    // ---------------------------------------------------------------------

    /**
     * The stored count is deliberately wrong before the rename, because
     * asserting only that it is *unchanged* would pass with no `isDirty` guard
     * at all — recounting the same contents returns the same number either way.
     * A wrong value surviving the rename is the only thing that distinguishes a
     * guarded hook from an unguarded one.
     */
    public function test_renaming_a_scene_does_not_recount_its_contents(): void
    {
        $scene = Scene::factory()->create(['contents' => 'Five whole words in here']);

        // Written straight to the column so the hook never sees it.
        DB::table('scenes')->where('id', $scene->id)->update(['word_count' => 999]);

        $scene->fresh()->update(['name' => 'A brand new name']);

        $this->assertSame(999, $scene->fresh()->word_count);
    }

    // ---------------------------------------------------------------------
    // 8. Emptying contents sets word_count to 0
    // ---------------------------------------------------------------------

    public function test_emptying_contents_sets_word_count_to_zero(): void
    {
        $scene = Scene::factory()->create(['contents' => 'Some words that will be removed']);
        $this->assertGreaterThan(0, $scene->word_count);

        $scene->update(['contents' => '']);

        $this->assertSame(0, $scene->fresh()->word_count);
    }
}
