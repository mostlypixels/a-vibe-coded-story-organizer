<?php

namespace Tests\Unit\Import;

use App\Enums\ImportPhase;
use App\Exceptions\ImportAlreadyRunningException;
use App\Exceptions\ImportValidationException;
use App\Jobs\ProjectImportJob;
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
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/** Guard import checkpoints, cleanup, resume, and discard behavior. */
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

        // 'local' holds the uploaded archive + its extraction; 'media' is
        // where the codex phase copies media bytes to.
        Storage::fake('local');
        Storage::fake('media');
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

    public function test_start_returns_a_pending_import_with_the_archive_stored_but_not_extracted(): void
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

        // ...but not extracted: the long unpack belongs to run(), not the request.
        $this->assertDirectoryDoesNotExist($this->extractedPath($import));
    }

    public function test_run_extracts_the_archive_that_start_stored_and_completes(): void
    {
        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), User::factory()->create());

        $importer->run($import);

        $this->assertSame(ImportPhase::Completed, $import->refresh()->phase);
        $this->assertSame('Fixture project', $import->project?->name);
    }

    public function test_run_extracts_again_over_a_partial_unpack_left_by_a_killed_run(): void
    {
        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), User::factory()->create());
        // A killed unpack leaves only the side folder, with some of the files.
        mkdir($this->extractedPath($import).'.partial/data', 0777, true);
        file_put_contents($this->extractedPath($import).'.partial/data/manifest.json', '{');

        $importer->run($import);

        $this->assertSame(ImportPhase::Completed, $import->refresh()->phase);
        $this->assertDirectoryDoesNotExist($this->extractedPath($import).'.partial');
    }

    public function test_an_extraction_failure_saves_a_failure_message_and_keeps_the_import(): void
    {
        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), User::factory()->create());
        Storage::disk('local')->put($import->archive_path, 'no longer a zip');

        try {
            $importer->run($import);
            $this->fail('A broken archive must throw.');
        } catch (ImportValidationException) {
            // expected
        }

        $import->refresh();
        $this->assertSame(ImportPhase::Pending, $import->phase);
        $this->assertNotNull($import->failure_message);
        $this->assertNull($import->running_since);
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

        // No import ever creates history — every imported entity starts with
        // an empty revision trail, the same way a fresh entity's does.
        $this->assertSame(0, Revision::query()->count());

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
        $actIds = $project->acts()->pluck('acts.id')->all();
        $chapterIds = $project->acts()->firstOrFail()->chapters()->pluck('id')->all();
        $sceneIds = $project->acts()->firstOrFail()->chapters()->firstOrFail()->scenes()->pluck('id')->all();

        // Resume with the REAL graph importer (the stall used a partial mock).
        $this->realImporter()->run($import);

        $this->assertSame(ImportPhase::Completed, $import->refresh()->phase);
        $this->assertNull($import->failure_message);

        // The story tree is byte-for-byte the same rows — no duplicates.
        $this->assertSame($actIds, $project->acts()->pluck('acts.id')->all());
        $this->assertSame($chapterIds, $project->acts()->firstOrFail()->chapters()->pluck('id')->all());
        $this->assertSame($sceneIds, $project->acts()->firstOrFail()->chapters()->firstOrFail()->scenes()->pluck('id')->all());
        $this->assertSame(1, Project::count());

        // Only the codex rows are new.
        $this->assertSame(1, $project->tags()->count());
        $this->assertSame(1, $project->codexAttributes()->count());
        $this->assertSame(1, $project->codexEntries()->count());

        // Completion cleaned the working files up.
        Storage::disk('local')->assertMissing($import->archive_path);
    }

    // ------------------------------------------------------------------
    // run() — the run claim
    // ------------------------------------------------------------------

    public function test_run_refuses_an_import_with_a_fresh_claim(): void
    {
        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), User::factory()->create());
        $claimedAt = now()->subMinute()->startOfSecond();
        $import->forceFill(['running_since' => $claimedAt])->save();

        try {
            $importer->run($import);
            $this->fail('A fresh claim must block a second run.');
        } catch (ImportAlreadyRunningException $exception) {
            $this->assertSame('This import is already running.', $exception->getMessage());
        }

        $import->refresh();
        $this->assertSame(ImportPhase::Pending, $import->phase);
        $this->assertSame(0, Project::count());
        // The other run keeps its claim.
        $this->assertTrue($claimedAt->equalTo($import->running_since));
    }

    public function test_run_takes_over_a_stale_claim_and_completes(): void
    {
        config(['import.timeout_seconds' => 1800]);

        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), User::factory()->create());
        $import->forceFill(['running_since' => now()->subSeconds(1801)])->save();

        $importer->run($import);

        $import->refresh();
        $this->assertSame(ImportPhase::Completed, $import->phase);
        $this->assertNull($import->running_since);
    }

    public function test_run_clears_the_claim_and_the_queue_time(): void
    {
        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), User::factory()->create());
        $import->update(['queued_at' => now()]);

        $importer->run($import);

        $import->refresh();
        $this->assertNull($import->running_since);
        $this->assertNull($import->queued_at);
    }

    public function test_the_claim_is_held_during_the_run_and_the_queue_time_is_cleared_when_it_starts(): void
    {
        $graphImporter = Mockery::mock(
            ProjectGraphImporter::class,
            [
                app(ContentSanitizer::class),
                app(CodexMediaService::class),
                app(CoverImageService::class),
            ],
        )->makePartial();

        $seen = null;
        $graphImporter->shouldReceive('importTimeline')->andReturnUsing(function () use (&$seen): void {
            $seen = Import::firstOrFail();
            throw new RuntimeException('stop');
        });
        $this->instance(ProjectGraphImporter::class, $graphImporter);

        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), User::factory()->create());
        $import->update(['queued_at' => now()]);

        try {
            $importer->run($import);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNotNull($seen);
        $this->assertTrue($seen->isRunning());
        $this->assertNull($seen->queued_at);
    }

    public function test_run_releases_the_claim_after_a_phase_exception(): void
    {
        $import = $this->startAndStallAtCodex(User::factory()->create());

        $this->assertSame(ImportPhase::Story, $import->phase);
        $this->assertNull($import->running_since);
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
    // ProjectImportJob
    // ------------------------------------------------------------------

    public function test_the_job_runs_once_with_the_import_timeout(): void
    {
        config(['import.timeout_seconds' => 1234]);

        $job = new ProjectImportJob(Import::factory()->create());

        $this->assertSame(1, $job->tries);
        $this->assertTrue($job->failOnTimeout);
        $this->assertTrue($job->deleteWhenMissingModels);
        $this->assertSame(1234, $job->timeout);
    }

    public function test_the_queue_payload_carries_the_import_timeout(): void
    {
        config(['import.timeout_seconds' => 1234, 'queue.default' => 'database']);

        ProjectImportJob::dispatch(Import::factory()->create());

        $payload = json_decode(DB::table('jobs')->value('payload'), true);

        // The worker reads this key. A timeout() method on the job never reaches it.
        $this->assertSame(1234, $payload['timeout']);
        $this->assertTrue($payload['failOnTimeout']);
    }

    public function test_the_job_on_a_running_import_returns_without_a_change(): void
    {
        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), User::factory()->create());
        $claimedAt = now()->subMinute()->startOfSecond();
        $import->forceFill(['running_since' => $claimedAt])->save();

        (new ProjectImportJob($import))->handle($importer);

        $import->refresh();
        $this->assertSame(ImportPhase::Pending, $import->phase);
        $this->assertNull($import->failure_message);
        $this->assertTrue($claimedAt->equalTo($import->running_since));
        $this->assertSame(0, Project::count());
    }

    public function test_a_timed_out_job_saves_a_message_clears_the_claim_and_stays_resumable(): void
    {
        config(['import.timeout_seconds' => 1800]);

        $importer = $this->importer();
        $import = $importer->start($this->makeValidUpload(), User::factory()->create());
        $import->forceFill(['running_since' => now()])->save();

        (new ProjectImportJob($import))->failed(new TimeoutExceededException('timed out'));

        $import->refresh();
        $this->assertSame(
            'The import took longer than 30 minutes and stopped. You can resume it or discard it.',
            $import->failure_message,
        );
        $this->assertNull($import->running_since);

        $importer->run($import);

        $this->assertSame(ImportPhase::Completed, $import->refresh()->phase);
    }

    public function test_a_failed_job_keeps_the_phase_failure_message(): void
    {
        $import = $this->startAndStallAtCodex(User::factory()->create());
        $message = $import->failure_message;
        $this->assertNotNull($message);

        (new ProjectImportJob($import))->failed(new RuntimeException('boom'));

        $import->refresh();
        $this->assertSame($message, $import->failure_message);
        $this->assertSame(ImportPhase::Story, $import->phase);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function importer(): ProjectImporter
    {
        return app(ProjectImporter::class);
    }

    private function extractedPath(Import $import): string
    {
        return Storage::disk('local')->path(Str::beforeLast($import->archive_path, '.zip'));
    }

    /**
     * A ProjectImporter wired to the REAL ProjectGraphImporter, bypassing any
     * partial mock a stall scenario bound into the container.
     */
    private function realImporter(): ProjectImporter
    {
        return new ProjectImporter(
            app(ArchiveValidator::class),
            // Constructed by hand, not resolved: a stall scenario binds a
            // partial mock into the container, and the resume has to use the
            // real thing.
            new ProjectGraphImporter(
                app(ContentSanitizer::class),
                app(CodexMediaService::class),
                app(CoverImageService::class),
            ),
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
            [
                app(ContentSanitizer::class),
                app(CodexMediaService::class),
                app(CoverImageService::class),
            ],
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
            'version' => 4, 'project_id' => 900,
            'exported_at' => '2026-07-13T00:00:00+00:00', 'includes_media' => true,
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

        // Story: one book, one act, one chapter, two scenes.
        $bookDir = 'data/books/50-fixture-book';
        $zip->addFromString("{$bookDir}/book.json", json_encode([
            'id' => 50, 'name' => null, 'position' => 1, 'project_id' => 900,
        ]));
        $zip->addFromString("{$bookDir}/acts/100-act-one/act.json", json_encode([
            'id' => 100, 'name' => 'Act One', 'position' => 1, 'book_id' => 50,
        ]));
        $zip->addFromString("{$bookDir}/acts/100-act-one/chapters/200-chapter-one/chapter.json", json_encode([
            'id' => 200, 'name' => 'Chapter One', 'position' => 1, 'act_id' => 100,
        ]));
        $sceneDir = "{$bookDir}/acts/100-act-one/chapters/200-chapter-one/scenes";
        $zip->addFromString("{$sceneDir}/300-scene-b/scene.json", json_encode([
            'id' => 300, 'name' => 'Scene B', 'position' => 2, 'status' => 'draft',
            'chapter_id' => 200, 'event_id' => 800, 'mentioned_event_ids' => [801],
            'contents_file' => 'contents.md',
        ]));
        $zip->addFromString("{$sceneDir}/300-scene-b/contents.md", 'Some *prose* here.');
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
