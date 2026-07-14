<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Enums\CodexMediaCollection;
use App\Enums\ImportPhase;
use App\Enums\SceneStatus;
use App\Http\Controllers\ImportController;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Event;
use App\Models\Import;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Tag;
use App\Models\User;
use App\Services\Import\ProjectGraphImporter;
use App\Services\ProjectImporter;
use App\Services\SceneReferenceMatcher;
use App\Services\StaticSiteExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The import feature's acceptance test (task 09): a full export -> import
 * round-trip exercising the WHOLE stack with nothing mocked. A non-trivial
 * project is seeded with factories, exported through the real
 * {@see StaticSiteExporter}, and the resulting zip is imported through the real
 * HTTP route ({@see ImportController}) — the security gate,
 * the content sanitizer, the graph importer, the checkpoint record, and the
 * queued/synchronous dispatch all run for real.
 *
 * The seeded project deliberately hits every axis the round-trip must preserve
 * (overview.md -> Acceptance criteria; testing.md -> Round-trip):
 *   - acts/chapters/scenes whose authoring order disagrees with their position
 *     (proving position is replayed, never re-derived from insertion order);
 *   - a renamed main plotline + a custom plotline + a non-fixed event;
 *   - a codex entry with aliases, tags, attribute values anchored to DIFFERENT
 *     events, and cover + reference media.
 *
 * It also proves the two intentional postures: the import is owned by the
 * importing user (never the archive's original owner), and re-importing the same
 * zip a second time produces a second, name-disambiguated project rather than a
 * collision or an overwrite.
 */
class ImportRoundTripTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Temp export zips created during a test (the exporter writes real files
     * under storage/app/exports, unaffected by Storage::fake), removed here.
     *
     * @var array<int, string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 'local' holds the uploaded archive + its extraction; 'public' holds
        // the source media bytes the exporter reads AND the fresh copies the
        // codex import phase writes.
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Round-trip WITH media bytes
    // ------------------------------------------------------------------

    public function test_a_full_export_import_round_trip_reconstructs_the_whole_project(): void
    {
        $owner = User::factory()->create();
        $source = $this->seedSourceProject($owner);

        // Export the real archive, then import it through the full HTTP route as
        // a DIFFERENT user — the imported project must still be owned by the
        // importer, never the source owner.
        $zipPath = $this->exportZip($source, includeMedia: true);
        $importer = User::factory()->create();

        $this->actingAs($importer)
            ->post(route('admin.data.import'), ['archive' => $this->upload($zipPath)])
            ->assertRedirect(route('projects.show', $importer->projects()->sole()));

        $imported = $importer->projects()->sole();

        // ---- Ownership + top-level scalars -----------------------------
        $this->assertSame($importer->id, $imported->user_id, 'the imported project is owned by the importer, not the source owner');
        $this->assertSame($source->name, $imported->name);
        $this->assertSame($source->description, $imported->description);
        $this->assertSame(ImportPhase::Completed, Import::firstOrFail()->phase);

        // ---- Story tree: names, order (by position), and content -------
        // Source acts by position: Act One (1) then Act Two (2) — even though
        // Act Two was created first. The imported tree must read the same way.
        $this->assertSame(
            ['Act One', 'Act Two'],
            $imported->acts()->orderBy('position')->pluck('name')->all(),
        );

        $importedActOne = $imported->acts()->where('name', 'Act One')->firstOrFail();
        $importedChapter = $importedActOne->chapters()->orderBy('position')->firstOrFail();
        $this->assertSame('Chapter One', $importedChapter->name);

        // Scenes: authored B-before-A but positioned A(1) then B(2).
        $scenes = $importedChapter->scenes()->orderBy('position')->get();
        $this->assertSame(['Scene A', 'Scene B'], $scenes->pluck('name')->all());
        $this->assertSame([1, 2], $scenes->pluck('position')->all());

        $importedSceneA = $scenes->firstWhere('name', 'Scene A');
        $importedSceneB = $scenes->firstWhere('name', 'Scene B');
        $this->assertSame('Some *prose* with **bold**.', $importedSceneB->contents);
        $this->assertSame(SceneStatus::ToEdit, $importedSceneA->status);
        $this->assertSame('<p>Editor notes.</p>', $importedSceneA->notes);

        // ---- Cross-references resolve to NEW ids -----------------------
        // Scene B "happens during" the non-fixed Great Battle event; the id is a
        // fresh one on the new project, but the target event is the same event.
        $this->assertNotNull($importedSceneB->event);
        $this->assertSame('The Great Battle', $importedSceneB->event->title);
        $this->assertSame($imported->id, $importedSceneB->event->project_id);
        // ...and it mentions the Start bookend.
        $this->assertSame(
            [$source->startEvent()->title],
            $importedSceneB->mentionedEvents()->pluck('title')->all(),
        );

        // ---- Timeline: reconciled anchors + custom rows ----------------
        // The renamed main plotline is reconciled onto the new project's OWN
        // auto-created is_main row (its fields carried over, its id fresh).
        $importedMain = $imported->plotlines()->where('is_main', true)->sole();
        $this->assertSame('The Central Thread', $importedMain->name);
        $this->assertSame('#ef4444', $importedMain->color);

        $importedSide = $imported->plotlines()->where('name', 'Side Quest')->firstOrFail();
        $this->assertFalse($importedSide->is_main);
        $this->assertSame('#3b82f6', $importedSide->color);

        // The non-fixed event carries its title/datetime and BOTH plotline links.
        $importedBattle = $imported->events()->where('title', 'The Great Battle')->firstOrFail();
        $this->assertFalse($importedBattle->is_fixed);
        $this->assertEqualsCanonicalizing(
            ['The Central Thread', 'Side Quest'],
            $importedBattle->plotlines()->pluck('name')->all(),
        );

        // ---- Codex: entry, aliases, tags, anchored attribute values ----
        $importedEntry = $imported->codexEntries()->sole();
        $this->assertSame('Alice Harker', $importedEntry->name);
        $this->assertSame(CodexEntryType::Character, $importedEntry->type);
        $this->assertSame('<p>The <em>hero</em>.</p>', $importedEntry->description);
        $this->assertEqualsCanonicalizing(
            ['Ally', 'The Wanderer'],
            $importedEntry->aliases()->pluck('alias')->all(),
        );
        $this->assertSame(['protagonist'], $importedEntry->tags()->pluck('name')->all());

        // Two attribute values for the SAME attribute anchored to DIFFERENT
        // events — the step function over time — each pointing at a new-project
        // event id resolved through the id map.
        $importedValues = $importedEntry->attributeValues()->with('startEvent')->get();
        $this->assertCount(2, $importedValues);
        $valuesByEventTitle = $importedValues->mapWithKeys(
            fn (CodexAttributeValue $value) => [$value->startEvent->title => $value->value],
        );
        $this->assertSame('20', $valuesByEventTitle[$source->startEvent()->title]);
        $this->assertSame('29', $valuesByEventTitle['The Great Battle']);

        // ---- Media: metadata AND bytes copied to a fresh path ----------
        $importedMedia = $importedEntry->media()->orderBy('collection')->get();
        $this->assertCount(2, $importedMedia);

        $importedCover = $importedMedia->firstWhere('collection', CodexMediaCollection::Cover);
        $this->assertSame('portrait.jpg', $importedCover->original_name);
        $this->assertSame('image/jpeg', $importedCover->mime_type);
        // A brand-new storage path (never the archive's) holding the exact bytes.
        $this->assertNotNull($importedCover->path);
        Storage::disk('public')->assertExists($importedCover->path);
        $this->assertSame(
            Storage::disk('public')->get($source->codexEntries()->sole()->cover()->firstOrFail()->path),
            Storage::disk('public')->get($importedCover->path),
        );

        // ---- Domain invariants hold on the new project -----------------
        $this->assertSame(1, $imported->plotlines()->where('is_main', true)->count());
        $this->assertSame(2, $imported->events()->where('is_fixed', true)->count());
        $this->assertContiguousPositions($scenes->pluck('position')->all());

        // ------------------------------------------------------------------
        // Re-import the SAME zip a second time (same importer): a distinct,
        // name-disambiguated project — never a collision or an overwrite.
        // ------------------------------------------------------------------
        $this->actingAs($importer)
            ->post(route('admin.data.import'), ['archive' => $this->upload($zipPath)]);

        $this->assertSame(2, $importer->projects()->count(), 'a second import creates a second project, never overwrites the first');
        $second = $importer->projects()->orderByDesc('id')->firstOrFail();
        $this->assertNotSame($imported->id, $second->id);
        // The collision only ever renames the NEW project (timestamp suffix).
        $this->assertNotSame($imported->name, $second->name);
        $this->assertStringStartsWith($source->name.' (imported ', $second->name);
    }

    // ------------------------------------------------------------------
    // Round-trip WITHOUT media bytes (metadata-only export)
    // ------------------------------------------------------------------

    public function test_a_round_trip_without_media_keeps_metadata_but_creates_no_files(): void
    {
        $owner = User::factory()->create();
        $source = $this->seedSourceProject($owner);

        // Export with the "Include images & files" toggle OFF: every media row's
        // metadata is written, but no bytes.
        $zipPath = $this->exportZip($source, includeMedia: false);
        $importer = User::factory()->create();

        $this->actingAs($importer)
            ->post(route('admin.data.import'), ['archive' => $this->upload($zipPath)])
            ->assertSessionHasNoErrors();

        $imported = $importer->projects()->sole();
        $importedMedia = $imported->codexEntries()->sole()->media()->get();

        // The rows still exist with correct metadata...
        $this->assertCount(2, $importedMedia);
        $this->assertEqualsCanonicalizing(
            ['portrait.jpg', 'sketch.png'],
            $importedMedia->pluck('original_name')->all(),
        );

        // ...but no byte file was written for any of them, and nothing errored
        // trying to read a file that isn't there.
        foreach ($importedMedia as $media) {
            $this->assertNull($media->path, "{$media->original_name} must have a null path with no bytes exported");
            $this->assertFalse($media->hasFile());
        }

        $this->assertSame(ImportPhase::Completed, Import::firstOrFail()->phase);
    }

    // ------------------------------------------------------------------
    // Codex reference regeneration (task 07)
    // ------------------------------------------------------------------

    /**
     * The archive NEVER carries scene_codex_entry (a derived cache). Proving
     * regeneration, not copying: the source project's pivot row comes from a
     * native save (the matcher), the exporter writes none, and the imported
     * project's row can therefore only exist because run() recomputed it after
     * the graph-import phases.
     */
    public function test_import_regenerates_scene_codex_references_that_the_archive_never_carried(): void
    {
        $owner = User::factory()->create();
        [$project, $chapter] = $this->seedReferenceSkeleton($owner);

        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Alice Harker']);
        $entry->aliases()->create(['alias' => 'Ally']);

        $scene = Scene::factory()->for($chapter)->create([
            'name' => 'A Meeting', 'position' => 1,
            'contents' => 'Ally arrives at dawn and everything changes.',
        ]);

        // Native-save equivalent: the source gets its pivot row from the matcher.
        app(SceneReferenceMatcher::class)->syncProject($project);
        $this->assertTrue(
            $scene->codexReferences()->whereKey($entry->id)->exists(),
            'the source scene must reference the entry from its native save',
        );

        // The exported archive carries no reference data — the imported row can
        // only come from regeneration.
        $zipPath = $this->exportZip($project, includeMedia: false);
        $importer = User::factory()->create();

        $this->actingAs($importer)
            ->post(route('admin.data.import'), ['archive' => $this->upload($zipPath)])
            ->assertSessionHasNoErrors();

        $imported = $importer->projects()->sole();
        $importedScene = $imported->acts()->firstOrFail()
            ->chapters()->firstOrFail()
            ->scenes()->where('name', 'A Meeting')->firstOrFail();
        $importedEntry = $imported->codexEntries()->where('name', 'Alice Harker')->firstOrFail();

        $this->assertTrue(
            $importedScene->codexReferences()->whereKey($importedEntry->id)->exists(),
            'the imported scene must reference the imported entry, regenerated after import',
        );
        $this->assertSame(ImportPhase::Completed, Import::firstOrFail()->phase);
    }

    /**
     * Overlapping-alias scenario: one term ("Robin") is BOTH one entry's name
     * and another entry's alias. A scene mentioning it must link to BOTH after
     * import — exactly as a native save produces (both entries link
     * independently, no precedence).
     */
    public function test_import_regenerates_overlapping_alias_references_to_every_matching_entry(): void
    {
        $owner = User::factory()->create();
        [$project, $chapter] = $this->seedReferenceSkeleton($owner);

        $namedRobin = CodexEntry::factory()->for($project)->character()->create(['name' => 'Robin']);
        $aliasedRobin = CodexEntry::factory()->for($project)->character()->create(['name' => 'Marian']);
        $aliasedRobin->aliases()->create(['alias' => 'Robin']);

        Scene::factory()->for($chapter)->create([
            'name' => 'The Forest', 'position' => 1,
            'contents' => 'Robin steps out of the shadows.',
        ]);

        $zipPath = $this->exportZip($project, includeMedia: false);
        $importer = User::factory()->create();

        $this->actingAs($importer)
            ->post(route('admin.data.import'), ['archive' => $this->upload($zipPath)])
            ->assertSessionHasNoErrors();

        $imported = $importer->projects()->sole();
        $importedScene = $imported->acts()->firstOrFail()
            ->chapters()->firstOrFail()
            ->scenes()->where('name', 'The Forest')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['Robin', 'Marian'],
            $importedScene->codexReferences()->pluck('name')->all(),
            'a scene mentioning an overlapping term must link to every matching entry',
        );
    }

    /**
     * Resumability: the regeneration hook sits AFTER the remainingPhases() loop,
     * so it must fire even on a resumed run() whose loop is empty. Simulate a
     * crash right after the Codex phase committed (phase = Codex, no pivot rows
     * yet) and prove the references appear and the import completes.
     */
    public function test_regeneration_fires_on_a_resumed_run_whose_phase_loop_is_empty(): void
    {
        $owner = User::factory()->create();
        [$project, $chapter] = $this->seedReferenceSkeleton($owner);

        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Alice Harker']);
        $scene = Scene::factory()->for($chapter)->create([
            'name' => 'A Meeting', 'position' => 1,
            'contents' => 'Alice Harker walks the long road.',
        ]);

        // The graph is fully on disk (Codex committed) but the derived cache was
        // never written — exactly the state a crash between the Codex checkpoint
        // and Completed leaves. No pivot rows yet.
        $this->assertDatabaseCount('scene_codex_entry', 0);

        // A resumed run() re-extracts only if the extraction dir is missing; a
        // real crash-resume keeps it, so we recreate it (empty is fine — the
        // empty phase loop reads nothing from disk).
        $extractionDir = 'imports/'.$project->getKey().'-resume';
        Storage::disk('local')->makeDirectory($extractionDir);

        $import = $owner->imports()->create([
            'project_id' => $project->id,
            'archive_path' => $extractionDir.'.zip',
            'archive_original_name' => 'resume.zip',
            'phase' => ImportPhase::Codex,
        ]);

        app(ProjectImporter::class)->run($import->refresh());

        $this->assertTrue(
            $scene->codexReferences()->whereKey($entry->id)->exists(),
            'the post-loop hook must regenerate references on a resumed, empty-loop run',
        );
        $this->assertSame(ImportPhase::Completed, $import->refresh()->phase);
    }

    /**
     * An import that fails before the Codex phase never reaches the regeneration
     * hook, so it leaves no partial/stale reference rows — nothing to sync yet,
     * nothing to clean up.
     */
    public function test_an_import_that_fails_before_codex_writes_no_reference_rows(): void
    {
        $owner = User::factory()->create();
        [$project, $chapter] = $this->seedReferenceSkeleton($owner);

        CodexEntry::factory()->for($project)->character()->create(['name' => 'Alice Harker']);
        Scene::factory()->for($chapter)->create([
            'name' => 'A Meeting', 'position' => 1,
            'contents' => 'Alice Harker walks the long road.',
        ]);

        // Extraction dir present so run() reaches the phase loop rather than
        // re-extracting from a (non-existent) archive.
        $extractionDir = 'imports/'.$project->getKey().'-fail';
        Storage::disk('local')->makeDirectory($extractionDir);

        // Sit at Timeline so the next phase is Story; make Story throw before
        // the Codex phase (and thus before the regeneration hook) is ever run.
        $import = $owner->imports()->create([
            'project_id' => $project->id,
            'archive_path' => $extractionDir.'.zip',
            'archive_original_name' => 'fail.zip',
            'phase' => ImportPhase::Timeline,
        ]);

        $graphImporter = $this->mock(ProjectGraphImporter::class);
        $graphImporter->shouldReceive('importStory')->once()
            ->andThrow(new \RuntimeException('boom during story'));

        try {
            app(ProjectImporter::class)->run($import->refresh());
            $this->fail('run() should have rethrown the Story-phase failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('boom during story', $exception->getMessage());
        }

        $this->assertDatabaseCount('scene_codex_entry', 0);
        $this->assertNotSame(ImportPhase::Completed, $import->refresh()->phase);
    }

    // ------------------------------------------------------------------
    // Fixtures & helpers
    // ------------------------------------------------------------------

    /**
     * A minimal exportable project skeleton (project → act → chapter) for the
     * reference-regeneration tests, which add their own scenes and codex
     * entries. Returns [Project, Chapter].
     *
     * @return array{0: Project, 1: Chapter}
     */
    private function seedReferenceSkeleton(User $owner): array
    {
        $project = Project::factory()->for($owner)->create();
        $act = Act::factory()->for($project)->create(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['position' => 1]);

        return [$project, $chapter];
    }

    /**
     * Seed one non-trivial project covering every round-trip axis. Returns the
     * refreshed project (its description is sanitized on write, so we compare
     * against the stored value, exactly as ExportTest does).
     */
    private function seedSourceProject(User $owner): Project
    {
        $project = Project::factory()->for($owner)->create([
            'name' => 'The Round Trip Chronicle',
            'description' => '<p>An <strong>epic</strong> tale.</p>',
        ]);

        // Rename + restyle the auto-created is_main plotline (reconciliation axis).
        $main = $project->plotlines()->where('is_main', true)->firstOrFail();
        $main->update(['name' => 'The Central Thread', 'color' => '#ef4444']);

        // A custom, non-main plotline.
        $side = Plotline::factory()->for($project)->create([
            'name' => 'Side Quest', 'color' => '#3b82f6', 'is_main' => false,
        ]);

        // A non-fixed event on both plotlines.
        $battle = Event::factory()->for($project)->create([
            'title' => 'The Great Battle',
            'event_datetime' => '2026-05-01 09:30:00',
            'is_fixed' => false,
        ]);
        $battle->plotlines()->attach([$main->id, $side->id]);

        // Story tree with authoring order deliberately NOT matching position:
        // Act Two is created first but positioned second.
        $actTwo = Act::factory()->for($project)->create(['name' => 'Act Two', 'position' => 2]);
        $actOne = Act::factory()->for($project)->create(['name' => 'Act One', 'position' => 1]);
        Chapter::factory()->for($actTwo)->create(['name' => 'Chapter Two', 'position' => 1]);
        $chapter = Chapter::factory()->for($actOne)->create(['name' => 'Chapter One', 'position' => 1]);

        // Scene B authored before Scene A but positioned second.
        $sceneB = Scene::factory()->for($chapter)->create([
            'name' => 'Scene B', 'position' => 2, 'status' => SceneStatus::Draft,
            'contents' => 'Some *prose* with **bold**.', 'event_id' => $battle->id,
        ]);
        Scene::factory()->for($chapter)->create([
            'name' => 'Scene A', 'position' => 1, 'status' => SceneStatus::ToEdit,
            'contents' => 'Opening lines.', 'notes' => '<p>Editor notes.</p>',
        ]);
        $sceneB->mentionedEvents()->attach($project->startEvent());

        // Codex: entry with aliases, a tag, anchored attribute values, and media.
        $tag = Tag::factory()->for($project)->create(['name' => 'protagonist']);
        $attribute = CodexAttribute::factory()->for($project)
            ->appliesTo(CodexEntryType::Character)
            ->create(['name' => 'Age']);

        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Alice Harker', 'description' => '<p>The <em>hero</em>.</p>',
        ]);
        $entry->aliases()->create(['alias' => 'Ally']);
        $entry->aliases()->create(['alias' => 'The Wanderer']);
        $entry->tags()->attach($tag);

        // Two values for one attribute, anchored to DIFFERENT events.
        CodexAttributeValue::factory()->create([
            'codex_entry_id' => $entry->id, 'codex_attribute_id' => $attribute->id,
            'start_event_id' => $project->startEvent()->id, 'value' => '20',
        ]);
        CodexAttributeValue::factory()->create([
            'codex_entry_id' => $entry->id, 'codex_attribute_id' => $attribute->id,
            'start_event_id' => $battle->id, 'value' => '29',
        ]);

        // Cover + reference image, both genuine images (so the import security
        // gate's content sniff passes). `size` is set to the ACTUAL byte size so
        // the archive validator's declared-vs-actual size check passes on import.
        $coverPath = UploadedFile::fake()->image('portrait.jpg', 20, 20)->store('codex-media', 'public');
        CodexMedia::factory()->cover()->for($entry, 'entry')->create([
            'path' => $coverPath, 'original_name' => 'portrait.jpg', 'mime_type' => 'image/jpeg',
            'size' => Storage::disk('public')->size($coverPath),
        ]);
        $sketchPath = UploadedFile::fake()->image('sketch.png', 20, 20)->store('codex-media', 'public');
        CodexMedia::factory()->referenceImage()->for($entry, 'entry')->create([
            'path' => $sketchPath, 'original_name' => 'sketch.png', 'mime_type' => 'image/png',
            'size' => Storage::disk('public')->size($sketchPath),
        ]);

        return $project->refresh();
    }

    /**
     * Build a real export archive via the service and return its path on disk
     * (registered for cleanup). Calling the service directly is the round-trip's
     * intended entry point (task 09 scope) — the export HTTP route is covered by
     * ExportTest.
     */
    private function exportZip(Project $project, bool $includeMedia): string
    {
        $path = app(StaticSiteExporter::class)->export($project, $includeMedia);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * A fresh UploadedFile over the persisted export zip, so the same archive can
     * be POSTed to the import route more than once in a single test.
     */
    private function upload(string $zipPath): UploadedFile
    {
        return new UploadedFile($zipPath, 'export.zip', 'application/zip', null, true);
    }

    /**
     * Assert a set of sibling positions is a contiguous 1..N sequence.
     *
     * @param  array<int, int>  $positions
     */
    private function assertContiguousPositions(array $positions): void
    {
        $this->assertSame(range(1, count($positions)), $positions);
    }
}
