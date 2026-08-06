<?php

namespace Tests\Feature;

use App\Enums\ChapterTitleFormat;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\PublicationSetting;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Epub export: the POST /admin/data/export/epub endpoint that wires the
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
     * Give a project one act → chapter → scene so it clears the exporter's
     * "no scenes anywhere" guard and actually produces a package.
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

    /**
     * Every file inside the exported EPUB whose name matches `$pattern`, as text.
     *
     * The response is never actually sent in a test, so `deleteFileAfterSend` has not
     * run and the streamed temp file is still on disk (same trick as ExportTest).
     */
    private function packagedFiles(TestResponse $response, string $pattern): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'The exported epub could not be opened.');

        $files = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (preg_match($pattern, $name)) {
                $files[$name] = $zip->getFromIndex($index);
            }
        }

        $zip->close();

        return $files;
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

    /**
     * A chapter whose name is blank, under the "Title only" format, used to produce an
     * empty label in the packaged navigation and table of contents — a blank row in the
     * reader's contents list. `validatePackage()` schema-checks the OPF, not the nav,
     * so the broken book exported clean.
     *
     * The page heading is deliberately still empty (the writer asked for the title
     * alone, and there is none); it is only the listing that gets the fallback.
     *
     * `chapters.name` is `NOT NULL` and `Store/UpdateChapterRequest` require it, so the
     * form cannot produce this — an import, a seeder or a direct write can. That is
     * exactly why the export must not depend on the form having been the door: the
     * blank-name branch in `ChapterTitleFormat::format()` exists for the same reason.
     */
    public function test_a_chapter_with_a_blank_name_still_gets_a_navigation_label(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $act = Act::factory()->for($project)->create(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['position' => 1, 'name' => '']);
        Scene::factory()->for($chapter)->create(['position' => 1, 'contents' => 'Some prose.']);

        PublicationSetting::factory()->for($project)->create([
            'chapter_title_format' => ChapterTitleFormat::Title,
        ]);

        $response = $this->actingAs($user)->post(route('admin.data.export.epub'), [
            'project_id' => $project->id,
        ])->assertOk();

        // Both listings the reader can reach: the EPUB 3 nav document / NCX the reader
        // chrome builds its contents menu from, and the in-book Table of Contents page.
        $listings = $this->packagedFiles($response, '/(nav|toc)[^\/]*\.(xhtml|ncx)$/i');

        $this->assertNotEmpty($listings, 'the export produced no navigation document to check');

        foreach ($listings as $name => $contents) {
            $this->assertStringContainsString(
                'Chapter 1',
                $contents,
                "[{$name}] a nameless chapter must fall back to its position, not list a blank row",
            );
        }
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
        // No acts/chapters/scenes at all.
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->from(route('admin.data.export-ebook'))
            ->post(route('admin.data.export.epub'), ['project_id' => $project->id]);

        $response->assertRedirect(route('admin.data.export-ebook'));
        $response->assertSessionHasErrors('project_id');
    }

    /**
     * An outline with no prose in it anywhere: the acts and chapters are exported when at
     * least one scene exists, but with zero scenes the book would be blank pages, so it is
     * the same user-facing redirect, not a 500.
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

    /**
     * The heart of "exports omit nothing": one written chapter among nine placeholders
     * exports all ten chapter pages, so the exported book's chapter numbers can never
     * disagree with the app's.
     */
    public function test_empty_chapters_export_as_heading_only_pages_alongside_the_written_ones(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['position' => 1]);

        $written = Chapter::factory()->for($act)->create(['position' => 1, 'name' => 'The Written One']);
        Scene::factory()->for($written)->create(['position' => 1, 'contents' => 'PROSE_MARKER']);

        $placeholders = [];
        for ($position = 2; $position <= 10; $position++) {
            $placeholders[] = Chapter::factory()->for($act)->create([
                'position' => $position,
                'name' => "Placeholder {$position}",
            ]);
        }

        $response = $this->actingAs($user)
            ->post(route('admin.data.export.epub'), ['project_id' => $project->id]);

        $response->assertOk();

        $chapterPages = $this->packagedFiles($response, '#chapter-\d+\.xhtml$#');
        $this->assertCount(10, $chapterPages, 'every chapter must export, written or not');

        // Filenames stay id-keyed, never number-keyed.
        $this->assertArrayHasKey("OEBPS/chapter-{$written->id}.xhtml", $chapterPages);

        foreach ($placeholders as $placeholder) {
            $page = $chapterPages["OEBPS/chapter-{$placeholder->id}.xhtml"] ?? null;
            $this->assertNotNull($page, "chapter {$placeholder->id} must have its own page");
            $this->assertStringContainsString("Chapter {$placeholder->position}: {$placeholder->name}", $page);
            // A heading and nothing else — no app-written "not written yet" filler.
            $this->assertStringNotContainsString('<p>', $page);
            $this->assertStringNotContainsString('<hr/>', $page);
        }
    }

    /**
     * An act whose chapters are all empty still exports its divider page — the act numbers
     * in the book stay in step with the app's for the same reason chapter numbers do.
     */
    public function test_an_act_with_only_empty_chapters_still_exports_its_divider(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $written = Act::factory()->for($project)->create(['position' => 1, 'name' => 'Written Act']);
        $chapter = Chapter::factory()->for($written)->create(['position' => 1]);
        Scene::factory()->for($chapter)->create(['position' => 1, 'contents' => 'Prose.']);

        $outlined = Act::factory()->for($project)->create(['position' => 2, 'name' => 'Outlined Act']);
        Chapter::factory()->for($outlined)->create(['position' => 1]);

        $bare = Act::factory()->for($project)->create(['position' => 3, 'name' => 'Bare Act']);

        $response = $this->actingAs($user)
            ->post(route('admin.data.export.epub'), ['project_id' => $project->id]);

        $response->assertOk();

        $actPages = $this->packagedFiles($response, '#act-\d+\.xhtml$#');
        $this->assertCount(3, $actPages);

        foreach ([$written, $outlined, $bare] as $act) {
            $page = $actPages["OEBPS/act-{$act->id}.xhtml"] ?? null;
            $this->assertNotNull($page, "act {$act->id} must have its own divider page");
            $this->assertStringContainsString("Act {$act->position}", $page);
            $this->assertStringContainsString($act->name, $page);
        }
    }

    // ---------------------------------------------------------------------
    // Export page — the "Epub export" section
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
