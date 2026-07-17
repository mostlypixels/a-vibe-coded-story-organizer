<?php

namespace Tests\Feature;

use App\Enums\ImportPhase;
use App\Jobs\ProjectImportJob;
use App\Models\Import;
use App\Models\ImportSetting;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * HTTP-layer tests for the import feature (task 06): the four admin routes
 * (store / resume / destroy / import-settings), their Form Requests, and the
 * two intentional authorization postures (any-authenticated-user for the initial
 * upload, real ImportPolicy ownership for resume/discard).
 *
 * Deep zip-structure edge cases live in the unit suite
 * (tests/Unit/Import/ArchiveValidatorTest.php etc.); here we assert only that a
 * service rejection surfaces as an `archive` field error rather than a 500, and
 * that the happy path creates a user-owned project.
 */
class ImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A minimal valid 1x1 PNG for the fixture's cover media bytes.
     */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * Temp fixture zips created during a test, removed in tearDown.
     *
     * @var array<int, string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 'local' holds the uploaded archive + its extraction; 'public' is where
        // the codex phase copies media bytes to.
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

    // ---------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------

    public function test_owner_imports_a_valid_archive_and_is_redirected_to_the_new_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('admin.data.import'), ['archive' => $this->makeValidUpload()]);

        // A brand-new project owned by the importing user, regardless of the
        // archive's original project_id/owner.
        $project = $user->projects()->firstOrFail();
        $this->assertSame('Fixture project', $project->name);
        $this->assertSame($user->id, $project->user_id);

        // The fixture archive is a manifest-version-1 export, pre-dating the four
        // front-/back-matter fields (task 02, epub-configuration): their `*_file`
        // links are absent from project.json, so they must import as null rather
        // than crash the graph importer.
        $this->assertNull($project->dedication);
        $this->assertNull($project->acknowledgements);
        $this->assertNull($project->preface);
        $this->assertNull($project->postface);

        $response->assertRedirect(route('projects.show', $project));

        // The import ran inline to completion within the request.
        $this->assertSame(ImportPhase::Completed, Import::firstOrFail()->phase);
    }

    // ---------------------------------------------------------------------
    // Authorization — guests
    // ---------------------------------------------------------------------

    public function test_a_guest_is_redirected_to_login_for_every_import_route(): void
    {
        $import = Import::factory()->create();

        $this->post(route('admin.data.import'))->assertRedirect(route('login'));
        $this->post(route('admin.data.imports.resume', $import))->assertRedirect(route('login'));
        $this->delete(route('admin.data.imports.destroy', $import))->assertRedirect(route('login'));
        $this->patch(route('admin.data.import-settings'))->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------------
    // The Data page exposes the singleton + the user's in-progress imports
    // ---------------------------------------------------------------------

    public function test_the_data_page_lists_only_the_users_non_completed_imports(): void
    {
        $user = User::factory()->create();
        $stalled = Import::factory()->for($user)->phase(ImportPhase::Story)->create();
        Import::factory()->for($user)->phase(ImportPhase::Completed)->create();
        // Another user's stalled import must not leak into this user's list.
        Import::factory()->phase(ImportPhase::Story)->create();

        $response = $this->actingAs($user)->get(route('admin.data.import.index'));

        $response->assertOk();
        $imports = $response->viewData('imports');
        $this->assertSame([$stalled->id], $imports->pluck('id')->all());
        $this->assertNotNull($response->viewData('importSetting'));
    }

    // ---------------------------------------------------------------------
    // The Import tab renders its real form + in-progress list (task 08)
    // ---------------------------------------------------------------------

    public function test_the_import_tab_renders_the_upload_form(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.data.import.index'));

        $response->assertOk();
        // The upload form posts to the import route and keeps a labelled file input.
        $response->assertSee(route('admin.data.import'));
        $response->assertSee(__('Archive (.zip)'));
        // The size hint reads the live singleton cap (default 100 MB from config).
        $response->assertSee(__('Up to :size MB', ['size' => intdiv(ImportSetting::current()->max_archive_kilobytes, 1024)]));
    }

    public function test_a_successful_synchronous_import_flashes_project_imported(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.data.import'), ['archive' => $this->makeValidUpload()])
            ->assertSessionHas('status', __('Project imported.'));
    }

    public function test_a_stalled_import_appears_in_the_list_with_its_phase_label_and_actions(): void
    {
        $user = User::factory()->create();
        $import = Import::factory()->for($user)->phase(ImportPhase::Story)->create();

        $response = $this->actingAs($user)->get(route('admin.data.import.index'));

        $response->assertOk();
        // The archive's original name and the human phase label are shown.
        $response->assertSee($import->archiveOriginalName());
        $response->assertSee(ImportPhase::Story->label());
        // Both Resume and Discard actions target this import.
        $response->assertSee(route('admin.data.imports.resume', $import));
        $response->assertSee(route('admin.data.imports.destroy', $import));
        $response->assertSee(__('Resume'));
        $response->assertSee(__('Discard'));
    }

    public function test_a_completed_import_leaves_the_in_progress_list_empty(): void
    {
        $owner = User::factory()->create();

        // A genuine pending import; resuming runs every phase to completion, after
        // which the DataTransferController filters it out of the list.
        $import = app(ProjectImporter::class)->start($this->makeValidUpload(), $owner);
        $this->actingAs($owner)->post(route('admin.data.imports.resume', $import));
        $this->assertSame(ImportPhase::Completed, $import->refresh()->phase);

        $this->actingAs($owner)
            ->get(route('admin.data.import.index'))
            ->assertDontSee(__('In-progress imports'));
    }

    public function test_the_import_settings_form_prefills_with_the_current_singleton_values(): void
    {
        ImportSetting::current()->update(['max_archive_kilobytes' => 50 * 1024]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.data.import.index'))
            // The MB input pre-fills with 50 (KB / 1024), not a hard-coded default.
            ->assertSee('value="50"', escape: false);
    }

    // ---------------------------------------------------------------------
    // Form Request validation failures (assertSessionHasErrors('archive'))
    // ---------------------------------------------------------------------

    public function test_import_without_a_file_fails_validation(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.data.import'), [])
            ->assertSessionHasErrors('archive');
    }

    public function test_import_of_a_non_zip_file_fails_validation(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.data.import'), [
                'archive' => UploadedFile::fake()->create('not-a-zip.zip', 5, 'text/plain'),
            ])
            ->assertSessionHasErrors('archive');
    }

    public function test_import_of_an_oversized_archive_fails_validation_against_the_live_cap(): void
    {
        // Lower the cap on the live singleton BELOW the fixture's size — proves
        // ImportProjectRequest reads ImportSetting::current() at validation time
        // (never a hard-coded/stale value).
        ImportSetting::current()->update(['max_archive_kilobytes' => 1]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.data.import'), ['archive' => $this->makeValidUpload()])
            ->assertSessionHasErrors('archive');

        // Rejected at the Form Request — nothing was ever stored or imported.
        $this->assertSame(0, Import::count());
        $this->assertSame(0, Project::count());
    }

    // ---------------------------------------------------------------------
    // Service-layer rejection surfaces as a field error, not a 500
    // ---------------------------------------------------------------------

    public function test_an_archive_that_fails_the_security_gate_redirects_back_with_an_archive_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('admin.data.import'), ['archive' => $this->makeZipSlipUpload()]);

        // A validation failure (here: a zip-slip entry) is a form problem, not a
        // 500 — it comes back with the message on the `archive` field, and no
        // project or import row is created.
        $response->assertSessionHasErrors('archive');
        $this->assertSame(0, Project::count());
        $this->assertSame(0, Import::count());
    }

    // ---------------------------------------------------------------------
    // resume / destroy — real ImportPolicy ownership
    // ---------------------------------------------------------------------

    public function test_owner_can_resume_a_stalled_import(): void
    {
        $owner = User::factory()->create();

        // A genuine pending import (validated, stored, extracted) — resuming it
        // runs every phase inline to completion.
        $import = app(ProjectImporter::class)->start($this->makeValidUpload(), $owner);

        $this->actingAs($owner)
            ->post(route('admin.data.imports.resume', $import))
            ->assertRedirect(route('admin.data.import.index'));

        $this->assertSame(ImportPhase::Completed, $import->refresh()->phase);
    }

    public function test_a_non_owner_cannot_resume_another_users_import(): void
    {
        $import = app(ProjectImporter::class)->start($this->makeValidUpload(), User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('admin.data.imports.resume', $import))
            ->assertForbidden();

        // Untouched — still pending, nothing imported by the intruder.
        $this->assertSame(ImportPhase::Pending, $import->refresh()->phase);
    }

    public function test_owner_can_discard_a_stalled_import(): void
    {
        $owner = User::factory()->create();
        $import = app(ProjectImporter::class)->start($this->makeValidUpload(), $owner);

        $this->actingAs($owner)
            ->delete(route('admin.data.imports.destroy', $import))
            ->assertRedirect(route('admin.data.import.index'));

        $this->assertSame(0, Import::count());
    }

    public function test_a_non_owner_cannot_discard_another_users_import(): void
    {
        $import = app(ProjectImporter::class)->start($this->makeValidUpload(), User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.data.imports.destroy', $import))
            ->assertForbidden();

        $this->assertSame(1, Import::count());
    }

    // ---------------------------------------------------------------------
    // Import settings — PATCH admin.data.import-settings
    // ---------------------------------------------------------------------

    public function test_an_authenticated_user_updates_the_import_settings_singleton(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('admin.data.import-settings'), [
                'max_archive_megabytes' => 50,
                'run_in_background' => '1',
            ])
            ->assertRedirect(route('admin.data.import.index'));

        $setting = ImportSetting::current();

        // 50 MB persisted as kilobytes (50 * 1024), background mode on.
        $this->assertSame(50 * 1024, $setting->max_archive_kilobytes);
        $this->assertTrue($setting->run_in_background);
        $this->assertSame(1, ImportSetting::count());
    }

    public function test_updating_the_cap_takes_effect_on_the_next_import_immediately(): void
    {
        $user = User::factory()->create();

        // Raise then lower via the admin form; the second value is what the very
        // next import must be validated against (not a stale cached one).
        $this->actingAs($user)->patch(route('admin.data.import-settings'), [
            'max_archive_megabytes' => 200,
        ]);

        // A 1 MB cap is still far larger than the tiny fixture, so this import
        // succeeds under the freshly-saved value.
        $this->actingAs($user)->patch(route('admin.data.import-settings'), [
            'max_archive_megabytes' => 1,
        ]);

        $this->assertSame(1024, ImportSetting::current()->max_archive_kilobytes);

        $this->actingAs($user)
            ->post(route('admin.data.import'), ['archive' => $this->makeValidUpload()])
            ->assertSessionHasNoErrors();

        $this->assertSame(ImportPhase::Completed, Import::firstOrFail()->phase);
    }

    public function test_a_guest_cannot_update_the_import_settings(): void
    {
        $this->patch(route('admin.data.import-settings'), ['max_archive_megabytes' => 5])
            ->assertRedirect(route('login'));
    }

    public function test_import_settings_requires_a_positive_integer_megabytes(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('admin.data.import-settings'), ['max_archive_megabytes' => 0])
            ->assertSessionHasErrors('max_archive_megabytes');
    }

    // ---------------------------------------------------------------------
    // Queued mode — ImportSetting.run_in_background (task 07)
    // ---------------------------------------------------------------------

    public function test_synchronous_mode_never_pushes_a_job_and_imports_inline(): void
    {
        Queue::fake();

        // Default is synchronous (run_in_background = false).
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.data.import'), ['archive' => $this->makeValidUpload()])
            ->assertRedirect(route('projects.show', Project::firstOrFail()));

        // Nothing queued; the project exists immediately after the request.
        Queue::assertNothingPushed();
        $this->assertSame(ImportPhase::Completed, Import::firstOrFail()->phase);
        $this->assertSame($user->id, Project::firstOrFail()->user_id);
    }

    public function test_background_mode_queues_the_job_and_defers_the_import(): void
    {
        Queue::fake();

        ImportSetting::current()->update(['run_in_background' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('admin.data.import'), ['archive' => $this->makeValidUpload()]);

        // Redirected to the Import tab with the "queued" flash — the graph phases
        // were deferred, so no project exists yet.
        $response->assertRedirect(route('admin.data.import.index'));
        $response->assertSessionHas('status', __('Import queued.'));
        Queue::assertPushed(ProjectImportJob::class);

        $import = Import::firstOrFail();
        $this->assertTrue($import->queued);
        $this->assertSame(ImportPhase::Pending, $import->phase);
        $this->assertSame(0, Project::count());

        // Simulate the worker running the job: the validated/extracted import
        // completes exactly as an inline run would.
        app(ProjectImporter::class)->run($import);

        $this->assertSame(ImportPhase::Completed, $import->refresh()->phase);
        $this->assertSame($user->id, Project::firstOrFail()->user_id);
    }

    public function test_a_failing_archive_never_reaches_the_queue_even_in_background_mode(): void
    {
        Queue::fake();

        ImportSetting::current()->update(['run_in_background' => true]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.data.import'), ['archive' => $this->makeZipSlipUpload()])
            ->assertSessionHasErrors('archive');

        // Validation is always synchronous — a rejected archive is a form error,
        // never a queued job, and leaves no row or project behind.
        Queue::assertNothingPushed();
        $this->assertSame(0, Import::count());
        $this->assertSame(0, Project::count());
    }

    public function test_resume_follows_the_current_toggle_value_not_the_original(): void
    {
        Queue::fake();

        $owner = User::factory()->create();

        // Started while synchronous (default) — but no phases run yet since we
        // never call the store route; start() alone leaves it pending.
        $import = app(ProjectImporter::class)->start($this->makeValidUpload(), $owner);
        $this->assertSame(ImportPhase::Pending, $import->phase);

        // Flip the toggle ON between the initial attempt and the resume.
        ImportSetting::current()->update(['run_in_background' => true]);

        $this->actingAs($owner)
            ->post(route('admin.data.imports.resume', $import))
            ->assertRedirect(route('admin.data.import.index'));

        // Resume honored the CURRENT (background) value: it queued rather than
        // running inline, so the import is still pending and now flagged queued.
        Queue::assertPushed(ProjectImportJob::class);
        $this->assertSame(ImportPhase::Pending, $import->refresh()->phase);
        $this->assertTrue($import->queued);
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    /**
     * A complete, valid export archive as an UploadedFile: manifest, project,
     * the two timeline anchors on the main plotline, one act → chapter → two
     * scenes, tags, one attribute, and one codex entry with a cover image whose
     * bytes are included. Mirrors the ProjectImporter service-test fixture.
     */
    private function makeValidUpload(): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'import-http-test');
        $this->tempFiles[] = $zipPath;

        $pngBytes = base64_decode(self::TINY_PNG_BASE64);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);

        $zip->addFromString('data/manifest.json', json_encode([
            'version' => 1, 'project_id' => 900,
            'exported_at' => '2026-07-13T00:00:00+00:00', 'includes_media' => true,
        ]));

        $zip->addFromString('data/project/project.json', json_encode([
            'id' => 900, 'name' => 'Fixture project', 'description_file' => 'description.html',
        ]));
        $zip->addFromString('data/project/description.html', '<p>A <strong>bold</strong> project.</p>');

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
        $zip->addFromString("{$sceneDir}/301-scene-a/scene.json", json_encode([
            'id' => 301, 'name' => 'Scene A', 'position' => 1, 'status' => 'draft',
            'chapter_id' => 200, 'event_id' => null, 'mentioned_event_ids' => [],
        ]));

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

    /**
     * A structurally-valid zip whose central directory carries a zip-slip entry
     * (`../escape.txt`) — a real archive (so it passes the Form Request's
     * `mimes:zip`) that ArchiveValidator must reject at the security gate.
     */
    private function makeZipSlipUpload(): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'import-http-slip');
        $this->tempFiles[] = $zipPath;

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFromString('data/manifest.json', json_encode(['version' => 1]));
        $zip->addFromString('../escape.txt', 'pwned');
        $zip->close();

        return new UploadedFile($zipPath, 'evil.zip', 'application/zip', null, true);
    }
}
