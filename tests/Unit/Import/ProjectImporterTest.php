<?php

namespace Tests\Unit\Import;

use App\Enums\ImportPhase;
use App\Enums\RevisionOrigin;
use App\Exceptions\ImportValidationException;
use App\Models\Import;
use App\Models\Project;
use App\Models\Revision;
use App\Models\User;
use App\Services\CodexMediaService;
use App\Services\CoverImageService;
use App\Services\Import\ArchiveValidator;
use App\Services\Import\ContentSanitizer;
use App\Services\Import\ProjectGraphImporter;
use App\Services\ProjectImporter;
use App\Services\SceneReferenceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Service-level tests for the ProjectImporter orchestrator (import task 05):
 * start()/run()/discard() are called directly with an UploadedFile / Import
 * model — no HTTP route involved (that is task 06).
 *
 * The scenarios pin the checkpoint contract from data-model.md: a validation
 * failure creates no row at all, a completed run cleans up its working files,
 * a mid-run failure leaves a resumable checkpoint (phase + id_maps + a safe
 * failure_message), resuming replays ONLY the remaining phases, and discard
 * rolls everything back.
 */
class ProjectImporterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A minimal valid 1x1 PNG for the fixture's cover media bytes.
     */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * Temp files created during a test (fixture zips), removed in tearDown.
     *
     * @var array<int, string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 'local' holds the uploaded archive + its extraction; 'public' is
        // where the codex phase copies media bytes to.
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
    // start()
    // ------------------------------------------------------------------

    public function test_start_returns_a_pending_import_with_the_archive_stored_and_extracted(): void
    {
        $user = User::factory()->create();

        $import = $this->importer()->start($this->makeValidUpload(), $user);

        $this->assertSame(ImportPhase::Pending, $import->phase);
        $this->assertSame($user->id, $import->user_id);
        $this->assertNull($import->project_id);
        $this->assertSame('my-export.zip', $import->archive_original_name);

        // The zip is stored under imports/ on the private disk...
        $this->assertStringStartsWith('imports/', $import->archive_path);
        Storage::disk('local')->assertExists($import->archive_path);

        // ...and extracted next to it, ready for run() to read.
        $extractedDirectory = Storage::disk('local')->path(Str::beforeLast($import->archive_path, '.zip'));
        $this->assertDirectoryExists($extractedDirectory);
        $this->assertFileExists($extractedDirectory.'/data/manifest.json');
        $this->assertFileExists($extractedDirectory.'/data/project/project.json');
    }

    public function test_start_on_an_invalid_archive_throws_and_creates_no_import_row(): void
    {
        $notAZip = tempnam(sys_get_temp_dir(), 'import-test');
        $this->tempFiles[] = $notAZip;
        file_put_contents($notAZip, 'this is not a zip archive');

        try {
            $this->importer()->start(
                new UploadedFile($notAZip, 'fake.zip', 'application/zip', null, true),
                User::factory()->create(),
            );
            $this->fail('An invalid archive must throw an ImportValidationException.');
        } catch (ImportValidationException) {
            // expected
        }

        // A validation failure never even reaches phase = pending: no row,
        // and nothing copied onto the private disk either.
        $this->assertSame(0, Import::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ------------------------------------------------------------------
    // run() — the happy path
    // ------------------------------------------------------------------

    public function test_run_completes_all_phases_and_deletes_the_working_files(): void
    {
        $user = User::factory()->create();
        $importer = $this->importer();

        $import = $importer->start($this->makeValidUpload(), $user);
        $importer->run($import);

        $this->assertSame(ImportPhase::Completed, $import->refresh()->phase);
        $this->assertNull($import->failure_message);

        // The full graph landed, owned by the importing user.
        $project = $import->project;
        $this->assertNotNull($project);
        $this->assertSame($user->id, $project->user_id);
        $this->assertSame('Fixture project', $project->name);
        $this->assertSame(1, $project->acts()->count());
        $this->assertSame(2, $project->acts()->firstOrFail()->chapters()->firstOrFail()->scenes()->count());
        $this->assertSame(1, $project->codexEntries()->count());
        $this->assertSame(1, $project->tags()->count());

        // The scene's revision sidecar (task 15) was imported too, as
        // origin: import, owned by the importing user.
        $scene = $project->acts()->firstOrFail()->chapters()->firstOrFail()->scenes()->where('name', 'Scene B')->firstOrFail();
        $revision = $scene->revisions()->where('field', 'contents')->firstOrFail();
        $this->assertSame(RevisionOrigin::Import, $revision->origin);
        $this->assertSame($user->id, $revision->user_id);

        // Nothing left to resume from — the working files are gone.
        Storage::disk('local')->assertMissing($import->archive_path);
        $this->assertDirectoryDoesNotExist(
            Storage::disk('local')->path(Str::beforeLast($import->archive_path, '.zip')),
        );
    }

    // ------------------------------------------------------------------
    // run() — a phase failure leaves a resumable checkpoint
    // ------------------------------------------------------------------

    public function test_a_codex_phase_failure_leaves_the_import_checkpointed_at_story(): void
    {
        $user = User::factory()->create();

        $import = $this->startAndStallAtCodex($user);

        $this->assertSame(ImportPhase::Story, $import->phase);

        // Everything up to (and including) the committed story phase exists...
        $project = $import->project;
        $this->assertNotNull($project);
        $this->assertSame(1, $project->acts()->count());
        $this->assertSame(2, $project->acts()->firstOrFail()->chapters()->firstOrFail()->scenes()->count());

        // ...and nothing from the rolled-back codex phase does.
        $this->assertSame(0, $project->tags()->count());
        $this->assertSame(0, $project->codexAttributes()->count());
        $this->assertSame(0, $project->codexEntries()->count());

        // The Story phase's own revision import already committed.
        $this->assertSame(1, Revision::query()->where('origin', RevisionOrigin::Import)->count());

        // The failure message is safe to display: it names the phase, never
        // the internal exception text.
        $this->assertStringContainsString('codex', (string) $import->failure_message);
        $this->assertStringNotContainsString('boom', (string) $import->failure_message);

        // The working files survive the crash — that is what a resume runs from.
        Storage::disk('local')->assertExists($import->archive_path);
        $this->assertDirectoryExists(
            Storage::disk('local')->path(Str::beforeLast($import->archive_path, '.zip')),
        );
    }

    public function test_running_a_stalled_import_again_completes_only_the_remaining_phases(): void
    {
        $user = User::factory()->create();

        $import = $this->startAndStallAtCodex($user);
        $project = $import->project;

        // Snapshot the committed story tree: a resume must NOT recreate it.
        $actIds = $project->acts()->pluck('id')->all();
        $chapterIds = $project->acts()->firstOrFail()->chapters()->pluck('id')->all();
        $sceneIds = $project->acts()->firstOrFail()->chapters()->firstOrFail()->scenes()->pluck('id')->all();

        // Resume with the REAL graph importer (the stall used a partial mock).
        $this->realImporter()->run($import);

        $this->assertSame(ImportPhase::Completed, $import->refresh()->phase);
        $this->assertNull($import->failure_message);

        // The story tree is byte-for-byte the same rows — no duplicates.
        $this->assertSame($actIds, $project->acts()->pluck('id')->all());
        $this->assertSame($chapterIds, $project->acts()->firstOrFail()->chapters()->pluck('id')->all());
        $this->assertSame($sceneIds, $project->acts()->firstOrFail()->chapters()->firstOrFail()->scenes()->pluck('id')->all());
        $this->assertSame(1, Project::count());

        // Only the codex rows are new.
        $this->assertSame(1, $project->tags()->count());
        $this->assertSame(1, $project->codexAttributes()->count());
        $this->assertSame(1, $project->codexEntries()->count());

        // The Story phase's revision import committed BEFORE the stall — a
        // resume must not re-run it and duplicate the row (task 15's own
        // required test: a resumed import never duplicates revisions).
        $this->assertSame(1, Revision::query()->where('origin', RevisionOrigin::Import)->count());

        // Completion cleaned the working files up.
        Storage::disk('local')->assertMissing($import->archive_path);
    }

    // ------------------------------------------------------------------
    // discard()
    // ------------------------------------------------------------------

    public function test_discard_deletes_the_project_the_working_files_and_the_import_row(): void
    {
        $user = User::factory()->create();

        $import = $this->startAndStallAtCodex($user);
        $extractedDirectory = Storage::disk('local')->path(Str::beforeLast($import->archive_path, '.zip'));

        $this->realImporter()->discard($import);

        $this->assertSame(0, Project::count());
        $this->assertSame(0, Import::count());
        Storage::disk('local')->assertMissing($import->archive_path);
        $this->assertDirectoryDoesNotExist($extractedDirectory);
    }

    public function test_discard_before_any_phase_ran_needs_no_project_to_delete(): void
    {
        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), User::factory()->create());

        $importer->discard($import);

        $this->assertSame(0, Import::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function importer(): ProjectImporter
    {
        return app(ProjectImporter::class);
    }

    /**
     * A ProjectImporter wired to the REAL ProjectGraphImporter, bypassing any
     * partial mock a stall scenario bound into the container.
     */
    private function realImporter(): ProjectImporter
    {
        return new ProjectImporter(
            app(ArchiveValidator::class),
            new ProjectGraphImporter(app(ContentSanitizer::class), app(CodexMediaService::class), app(CoverImageService::class)),
            app(SceneReferenceMatcher::class),
        );
    }

    /**
     * Start a valid import, then run it with a ProjectGraphImporter whose
     * importCodex() throws — leaving the Import checkpointed at phase = story.
     * Returns the refreshed, stalled Import.
     */
    private function startAndStallAtCodex(User $user): Import
    {
        $graphImporter = Mockery::mock(
            ProjectGraphImporter::class,
            [app(ContentSanitizer::class), app(CodexMediaService::class), app(CoverImageService::class)],
        )->makePartial();
        $graphImporter->shouldReceive('importCodex')->andThrow(new RuntimeException('boom'));
        $this->instance(ProjectGraphImporter::class, $graphImporter);

        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), $user);

        try {
            $importer->run($import);
            $this->fail('The stubbed codex phase must throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage(), 'the phase exception must be rethrown, never swallowed');
        }

        return $import->refresh();
    }

    /**
     * A complete, valid export archive as an UploadedFile: manifest, project,
     * the two timeline anchors on the main plotline, one act → chapter → two
     * scenes (positions disagreeing with directory order), tags, one
     * attribute, and one codex entry with a cover image whose bytes are
     * included.
     */
    private function makeValidUpload(): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'import-test');
        $this->tempFiles[] = $zipPath;

        $pngBytes = base64_decode(self::TINY_PNG_BASE64);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);

        $zip->addFromString('data/manifest.json', json_encode([
            'version' => 1, 'project_id' => 900,
            'exported_at' => '2026-07-13T00:00:00+00:00', 'includes_media' => true,
            'includes_revisions' => true,
        ]));

        $zip->addFromString('data/project/project.json', json_encode([
            'id' => 900, 'name' => 'Fixture project', 'description_file' => 'description.html',
        ]));
        $zip->addFromString('data/project/description.html', '<p>A <strong>bold</strong> project.</p>');

        // Timeline: the main plotline and the two fixed bookends the graph
        // importer reconciles onto the auto-created anchor rows.
        $zip->addFromString('data/timeline/plotlines/700-central-arc/plotline.json', json_encode([
            'id' => 700, 'name' => 'Central Arc', 'color' => '#ef4444', 'is_main' => true, 'project_id' => 900,
        ]));
        $zip->addFromString('data/timeline/events/800-dawn-of-time/event.json', json_encode([
            'id' => 800, 'title' => 'Dawn of Time', 'event_datetime' => '0001-01-01T00:00:00+00:00',
            'is_fixed' => true, 'project_id' => 900, 'plotline_ids' => [700],
        ]));
        $zip->addFromString('data/timeline/events/801-heat-death/event.json', json_encode([
            'id' => 801, 'title' => 'Heat Death', 'event_datetime' => '3000-01-01T00:00:00+00:00',
            'is_fixed' => true, 'project_id' => 900, 'plotline_ids' => [700],
        ]));

        // Story: one act, one chapter, two scenes.
        $zip->addFromString('data/acts/100-act-one/act.json', json_encode([
            'id' => 100, 'name' => 'Act One', 'position' => 1, 'project_id' => 900,
        ]));
        $zip->addFromString('data/acts/100-act-one/chapters/200-chapter-one/chapter.json', json_encode([
            'id' => 200, 'name' => 'Chapter One', 'position' => 1, 'act_id' => 100,
        ]));
        $sceneDir = 'data/acts/100-act-one/chapters/200-chapter-one/scenes';
        $zip->addFromString("{$sceneDir}/300-scene-b/scene.json", json_encode([
            'id' => 300, 'name' => 'Scene B', 'position' => 2, 'status' => 'draft',
            'chapter_id' => 200, 'event_id' => 800, 'mentioned_event_ids' => [801],
            'contents_file' => 'contents.md',
        ]));
        $zip->addFromString("{$sceneDir}/300-scene-b/contents.md", 'Some *prose* here.');
        // A revision sidecar for Scene B's contents — the Story phase commits
        // this row BEFORE the codex-phase stall in the tests below, so it is
        // the one thing that proves a resume never re-runs an already
        // committed phase's revision import a second time.
        $zip->addFromString("{$sceneDir}/300-scene-b/revisions/contents.json", json_encode([
            ['id' => 1, 'value' => 'An earlier draft.', 'origin' => 'manual', 'label' => null, 'user_id' => 999999, 'created_at' => '2020-01-01T00:00:00+00:00'],
        ]));
        $zip->addFromString("{$sceneDir}/301-scene-a/scene.json", json_encode([
            'id' => 301, 'name' => 'Scene A', 'position' => 1, 'status' => 'draft',
            'chapter_id' => 200, 'event_id' => null, 'mentioned_event_ids' => [],
        ]));

        // Codex: a tag, an attribute, and one entry using both plus a cover.
        $zip->addFromString('data/tags.json', json_encode([
            ['id' => 600, 'name' => 'protagonist'],
        ]));
        $zip->addFromString('data/codex/attributes.json', json_encode([
            ['id' => 500, 'name' => 'Age', 'applies_to' => ['character'], 'position' => 1],
        ]));
        $zip->addFromString('data/codex/character/400-alice-harker/entry.json', json_encode([
            'id' => 400, 'name' => 'Alice Harker', 'type' => 'character', 'project_id' => 900,
            'aliases' => ['Ally'], 'tag_ids' => [600],
            'attribute_values' => [
                ['id' => 1, 'attribute_id' => 500, 'start_event_id' => 800, 'value' => '29'],
            ],
            'media' => [[
                'id' => 71, 'collection' => 'cover', 'position' => 1,
                'original_name' => 'portrait.png', 'mime_type' => 'image/png',
                'size' => strlen($pngBytes), 'file' => 'cover/portrait.png',
            ]],
        ]));
        $zip->addFromString('data/codex/character/400-alice-harker/cover/portrait.png', $pngBytes);

        $zip->close();

        return new UploadedFile($zipPath, 'my-export.zip', 'application/zip', null, true);
    }
}
