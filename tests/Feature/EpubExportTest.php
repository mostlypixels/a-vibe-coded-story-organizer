<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Epub export (task 06): the POST /admin/data/export/epub endpoint that wires the
 * HTTP layer to EpubExporter.
 *
 * Mirrors ExportTest's posture (owner-succeeds / non-owner-403 / guest-login /
 * validation), plus the one epub-specific path: an empty-content project redirects
 * back with a session error (the EpubExportException → redirect translation)
 * instead of streaming a file.
 */
class EpubExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Give a project one act → chapter → scene so it survives the exporter's
     * skip-empty filter and actually produces a package.
     */
    private function seedExportableContent(Project $project): void
    {
        $act = Act::factory()->for($project)->create(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['position' => 1]);
        Scene::factory()->for($chapter)->create([
            'position' => 1,
            'contents' => 'Some prose for the chapter.',
        ]);
    }

    // ---------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------

    public function test_owner_can_export_a_project_as_an_epub_download(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'My Great Story']);
        $this->seedExportableContent($project);

        $response = $this->actingAs($user)->post(route('admin.data.export.epub'), [
            'project_id' => $project->id,
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/epub+zip');

        // Content-Disposition filename is <project-slug>-<Ymd>-<His>.epub.
        $this->assertMatchesRegularExpression(
            '/filename=.*my-great-story-\d{8}-\d{6}\.epub/',
            $response->headers->get('content-disposition')
        );

        // The streamed temp file exists on disk (deleteFileAfterSend has not run in a
        // test since the response is never actually sent).
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);
    }

    // ---------------------------------------------------------------------
    // Authorization (ownership, not just the admin gate)
    // ---------------------------------------------------------------------

    public function test_a_user_cannot_export_another_users_project_as_epub(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->seedExportableContent($project);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post(route('admin.data.export.epub'), ['project_id' => $project->id])
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $project = Project::factory()->create();

        $this->post(route('admin.data.export.epub'), ['project_id' => $project->id])
            ->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------------
    // Validation & missing/foreign ids
    // ---------------------------------------------------------------------

    /**
     * A missing project_id is a 403, not a validation error: EpubExportRequest's
     * authorize() runs before validation and resolves Project::find(null) to null,
     * so it fails the ownership check first (mirrors ExportRequest).
     */
    public function test_a_missing_project_id_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.data.export.epub'), [])
            ->assertForbidden();
    }

    public function test_a_nonexistent_project_id_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.data.export.epub'), ['project_id' => 999999])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Empty-content project: EpubExportException → redirect-back-with-error
    // ---------------------------------------------------------------------

    public function test_an_empty_project_redirects_back_with_an_error_instead_of_downloading(): void
    {
        $user = User::factory()->create();
        // No acts/chapters/scenes: nothing survives the skip-empty filter.
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->from(route('admin.data.export-ebook'))
            ->post(route('admin.data.export.epub'), ['project_id' => $project->id]);

        $response->assertRedirect(route('admin.data.export-ebook'));
        $response->assertSessionHasErrors('project_id');
    }

    /**
     * A chapter with no scenes is filtered out too — if that leaves the whole project
     * empty, it is the same user-facing redirect, not a 500.
     */
    public function test_a_project_whose_only_chapter_has_no_scenes_redirects_back(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['position' => 1]);
        Chapter::factory()->for($act)->create(['position' => 1]);

        $response = $this->actingAs($user)
            ->from(route('admin.data.export-ebook'))
            ->post(route('admin.data.export.epub'), ['project_id' => $project->id]);

        $response->assertRedirect(route('admin.data.export-ebook'));
        $response->assertSessionHasErrors('project_id');
    }

    // ---------------------------------------------------------------------
    // Export page — the "Epub export" section (task 07)
    // ---------------------------------------------------------------------

    /**
     * The export page renders the new Epub export section: its heading, a form that
     * posts to the epub route with a project picker, the download button, and the
     * epubcheck note/link the section must carry.
     */
    public function test_the_export_page_renders_the_epub_export_section(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Epub-able Tale']);

        $response = $this->actingAs($user)->get(route('admin.data.export-ebook'));

        $response->assertOk();
        $response->assertSeeText('Export ebook');
        $response->assertSee('Download EPUB');
        // The section posts to the dedicated epub route with the project picker.
        $response->assertSee('action="'.route('admin.data.export.epub').'"', false);
        $response->assertSee('id="epub_project_id"', false);
        $response->assertSee('Epub-able Tale');
        // The epubcheck note links out to the official validator.
        $response->assertSeeText('epubcheck');
        $response->assertSee('href="https://www.w3.org/publishing/epubcheck/"', false);
    }

    /**
     * With no projects, the whole panel shows only the shared empty state — the epub
     * section (like the zip form) must not render, so there is no epub picker to post.
     */
    public function test_a_user_with_no_projects_does_not_see_the_epub_export_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.data.export-ebook'));

        $response->assertOk();
        $response->assertSee('Create a project first to export it.');
        $response->assertDontSee('Download EPUB');
        $response->assertDontSee('id="epub_project_id"', false);
    }
}
