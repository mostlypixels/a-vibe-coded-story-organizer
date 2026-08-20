<?php

namespace Tests\Feature;

use App\Enums\BookLanguage;
use App\Enums\ChapterTitleFormat;
use App\Enums\CodexEntryType;
use App\Enums\SceneStatus;
use App\Enums\StoryOverviewMode;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\PublicationSetting;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Project export: the POST /admin/data/export endpoint, the exporter
 * skeleton (data/manifest.json), the Export form, and the ownership guard.
 *
 * Tests capture the streamed download's temp file and open it with ZipArchive,
 * then assert on entry names and contents.
 */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Open the exported zip written by the download response. In a test the
     * response is never actually sent, so deleteFileAfterSend has not run and the
     * temp file still exists on disk.
     */
    private function openExport(TestResponse $response): ZipArchive
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'The export zip could not be opened.');

        return $zip;
    }

    // ---------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------

    public function test_owner_can_export_a_project_as_a_zip_download(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'My Great Story']);

        $response = $this->actingAs($user)->post(route('admin.data.export'), [
            'project_id' => $project->id,
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');

        // Content-Disposition filename is <project-slug>-<Ymd>-<His>.zip.
        $this->assertMatchesRegularExpression(
            '/filename=.*my-great-story-\d{8}-\d{6}\.zip/',
            $response->headers->get('content-disposition')
        );
    }

    // ---------------------------------------------------------------------
    // Manifest
    // ---------------------------------------------------------------------

    public function test_export_contains_a_valid_data_manifest(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('admin.data.export'), [
            'project_id' => $project->id,
            'include_images' => '1',
        ]);

        $response->assertOk();

        $zip = $this->openExport($response);
        $raw = $zip->getFromName('data/manifest.json');
        $this->assertNotFalse($raw, 'data/manifest.json is missing from the export.');

        $manifest = json_decode($raw, true);
        $this->assertIsArray($manifest, 'data/manifest.json is not valid JSON.');
        // The word-count-challenges feature bumped this to 5 — see
        // StaticSiteExporter::DATA_VERSION.
        $this->assertSame(5, $manifest['version']);
        $this->assertSame($project->id, $manifest['project_id']);
        $this->assertTrue($manifest['includes_media']);
        $this->assertArrayHasKey('exported_at', $manifest);

        $zip->close();
    }

    public function test_manifest_records_media_toggle_off_when_unchecked(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // An unchecked checkbox is absent from the request → include_images false.
        $response = $this->actingAs($user)->post(route('admin.data.export'), [
            'project_id' => $project->id,
        ]);

        $response->assertOk();

        $zip = $this->openExport($response);
        $manifest = json_decode($zip->getFromName('data/manifest.json'), true);
        $this->assertFalse($manifest['includes_media']);

        $zip->close();
    }

    // ---------------------------------------------------------------------
    // Authorization — ownership, not just the admin gate
    // ---------------------------------------------------------------------

    public function test_a_user_cannot_export_another_users_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post(route('admin.data.export'), ['project_id' => $project->id])
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $project = Project::factory()->create();

        $this->post(route('admin.data.export'), ['project_id' => $project->id])
            ->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------------
    // Validation & missing/foreign ids
    // ---------------------------------------------------------------------

    /**
     * A missing project_id is a 403, not a validation error: ExportRequest's
     * authorize() runs before validation and resolves Project::find(null) to
     * null, so it fails the ownership check first: a foreign OR missing
     * project_id is a 403.
     */
    public function test_a_missing_project_id_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.data.export'), [])
            ->assertForbidden();
    }

    public function test_a_nonexistent_project_id_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.data.export'), ['project_id' => 999999])
            ->assertForbidden();
    }

    public function test_a_non_boolean_include_images_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('admin.data.export'), [
                'project_id' => $project->id,
                'include_images' => 'maybe',
            ])
            ->assertSessionHasErrors('include_images');
    }

    // ---------------------------------------------------------------------
    // Form render
    // ---------------------------------------------------------------------

    public function test_export_form_lists_the_users_projects(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Exportable Tale']);

        $other = Project::factory()->create(['name' => 'Someone Elses Tale']);

        $response = $this->actingAs($user)->get(route('admin.data.export-project'));

        $response->assertOk();
        $response->assertSee('name="project_id"', false);
        $response->assertSee('Include images & files');
        $response->assertDontSee('Include revision history');
        // The user's own project is offered...
        $response->assertSee('Exportable Tale');
        // ...but not another user's project.
        $response->assertDontSee('Someone Elses Tale');
    }

    public function test_a_user_with_no_projects_sees_the_empty_state_not_the_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.data.export-project'));

        $response->assertOk();
        $response->assertSee('Create a project first to export it.');
        $response->assertDontSee('name="project_id"', false);
    }

    // ---------------------------------------------------------------------
    // data/ Story branch: project + act → chapter → scene tree
    // ---------------------------------------------------------------------

    /**
     * Export the given project as its owner and return the opened zip. Shared by the
     * Story-branch tests below.
     */
    private function exportZip(User $user, Project $project): ZipArchive
    {
        $response = $this->actingAs($user)->post(route('admin.data.export'), [
            'project_id' => $project->id,
        ]);

        $response->assertOk();

        return $this->openExport($response);
    }

    // ---------------------------------------------------------------------
    // README.md — the zip's front door
    // ---------------------------------------------------------------------

    public function test_readme_carries_the_project_name_export_date_and_layer_guidance(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Tale of Two Folders']);

        $zip = $this->exportZip($user, $project);

        $readme = $zip->getFromName('README.md');
        $this->assertNotFalse($readme, 'README.md is missing from the export.');

        // Title = project name; a "date of export" row with today's date.
        $this->assertStringContainsString('# Tale of Two Folders', $readme);
        $this->assertStringContainsString('| Date of export | '.now()->format('Y-m-d').' |', $readme);

        // Points humans at books/ and machines at data/.
        $this->assertStringContainsString('books/', $readme);
        $this->assertStringContainsString('data/', $readme);

        $zip->close();
    }

    public function test_readme_renders_the_project_description_as_plain_text(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'Described Project',
            'description' => '<p>An epic about <strong>courage</strong>.</p><p>And loss.</p>',
        ]);
        // Descriptions are sanitized on write; compare against what the DB holds.
        $project->refresh();

        $zip = $this->exportZip($user, $project);
        $readme = $zip->getFromName('README.md');

        // The prose survives; the HTML tags do not.
        $this->assertStringContainsString('An epic about courage.', $readme);
        $this->assertStringContainsString('And loss.', $readme);
        $this->assertStringNotContainsString('<strong>', $readme);
        $this->assertStringNotContainsString('<p>', $readme);

        $zip->close();
    }

    public function test_readme_omits_the_description_block_when_there_is_none(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['description' => null]);

        $zip = $this->exportZip($user, $project);
        $readme = $zip->getFromName('README.md');

        // Still present and useful without a description: title + date + guidance.
        $this->assertNotFalse($readme);
        $this->assertStringContainsString('## What is in this archive', $readme);

        $zip->close();
    }

    public function test_story_tree_is_written_as_nested_entity_directories(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $act = Act::factory()->for($book)->create(['name' => 'First Act', 'position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'First Chapter', 'position' => 1]);
        $sceneOne = Scene::factory()->for($chapter)->create(['name' => 'Opening Scene', 'position' => 1]);
        $sceneTwo = Scene::factory()->for($chapter)->create(['name' => 'Closing Scene', 'position' => 2]);

        $zip = $this->exportZip($user, $project);

        $actDir = $this->bookDir($book)."/acts/{$act->id}-first-act";
        $chapterDir = "{$actDir}/chapters/{$chapter->id}-first-chapter";
        $sceneOneDir = "{$chapterDir}/scenes/{$sceneOne->id}-opening-scene";
        $sceneTwoDir = "{$chapterDir}/scenes/{$sceneTwo->id}-closing-scene";

        foreach ([
            "{$actDir}/act.json",
            "{$chapterDir}/chapter.json",
            "{$sceneOneDir}/scene.json",
            "{$sceneOneDir}/contents.md",
            "{$sceneTwoDir}/scene.json",
        ] as $entry) {
            $this->assertNotFalse($zip->getFromName($entry), "{$entry} is missing from the export.");
        }

        $zip->close();
    }

    public function test_project_json_is_present_and_carries_the_stable_id(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'The Whole Book']);

        $zip = $this->exportZip($user, $project);

        $project_json = json_decode($zip->getFromName('data/project/project.json'), true);
        $this->assertSame($project->id, $project_json['id']);
        $this->assertSame('The Whole Book', $project_json['name']);

        $zip->close();
    }

    public function test_the_four_frontmatter_markdown_fields_export_as_raw_field_files_on_the_book(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'The Whole Book']);
        $book = $project->books()->first();
        $book->update([
            'dedication' => 'For *everyone*.',
            'acknowledgements' => 'Thanks to my **editor**.',
            'preface' => 'A word before we begin.',
            'postface' => 'And so it ends.',
        ]);

        $zip = $this->exportZip($user, $project);

        $bookDir = $this->bookDir($book);
        $bookJson = json_decode($zip->getFromName("{$bookDir}/book.json"), true);
        $this->assertSame('dedication.md', $bookJson['dedication_file']);
        $this->assertSame('acknowledgements.md', $bookJson['acknowledgements_file']);
        $this->assertSame('preface.md', $bookJson['preface_file']);
        $this->assertSame('postface.md', $bookJson['postface_file']);

        // Raw Markdown, verbatim — never rendered/re-formatted.
        $this->assertSame('For *everyone*.', $zip->getFromName("{$bookDir}/dedication.md"));
        $this->assertSame('Thanks to my **editor**.', $zip->getFromName("{$bookDir}/acknowledgements.md"));
        $this->assertSame('A word before we begin.', $zip->getFromName("{$bookDir}/preface.md"));
        $this->assertSame('And so it ends.', $zip->getFromName("{$bookDir}/postface.md"));

        // The project's own descriptor never carries these fields any more —
        // they belong to the book now.
        $project_json = json_decode($zip->getFromName('data/project/project.json'), true);
        $this->assertArrayNotHasKey('dedication_file', $project_json);
        $this->assertArrayNotHasKey('acknowledgements_file', $project_json);
        $this->assertArrayNotHasKey('preface_file', $project_json);
        $this->assertArrayNotHasKey('postface_file', $project_json);

        $zip->close();
    }

    public function test_empty_frontmatter_fields_write_no_field_file_or_link_on_the_book(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $zip = $this->exportZip($user, $project);

        $bookDir = $this->bookDir($book);
        $bookJson = json_decode($zip->getFromName("{$bookDir}/book.json"), true);
        $this->assertArrayNotHasKey('dedication_file', $bookJson);
        $this->assertArrayNotHasKey('acknowledgements_file', $bookJson);
        $this->assertArrayNotHasKey('preface_file', $bookJson);
        $this->assertArrayNotHasKey('postface_file', $bookJson);
        $this->assertFalse($zip->locateName("{$bookDir}/dedication.md"));

        $zip->close();
    }

    public function test_act_and_chapter_json_carry_position_and_parent_id(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $act = Act::factory()->for($book)->create(['position' => 3]);
        $chapter = Chapter::factory()->for($act)->create(['position' => 2]);

        $zip = $this->exportZip($user, $project);

        $actJson = json_decode($zip->getFromName($this->bookDir($book)."/acts/{$act->id}-".Str::slug($act->name).'/act.json'), true);
        $this->assertSame($act->id, $actJson['id']);
        $this->assertSame(3, $actJson['position']);
        $this->assertSame($book->id, $actJson['book_id']);

        $chapterDir = $this->bookDir($book)."/acts/{$act->id}-".Str::slug($act->name)
            ."/chapters/{$chapter->id}-".Str::slug($chapter->name);
        $chapterJson = json_decode($zip->getFromName("{$chapterDir}/chapter.json"), true);
        $this->assertSame($chapter->id, $chapterJson['id']);
        $this->assertSame(2, $chapterJson['position']);
        $this->assertSame($act->id, $chapterJson['act_id']);

        $zip->close();
    }

    public function test_scene_json_carries_scalars_relationships_and_status_value(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();

        // The scene "happens during" one event and mentions another.
        $duringEvent = Event::factory()->for($project)->create();
        $mentionedEvent = Event::factory()->for($project)->create();

        $scene = Scene::factory()->for($chapter)->create([
            'name' => 'Pivotal Scene',
            'position' => 1,
            'status' => SceneStatus::ToEdit,
            'event_id' => $duringEvent->id,
        ]);
        $scene->mentionedEvents()->attach($mentionedEvent);

        $zip = $this->exportZip($user, $project);

        $sceneDir = $this->sceneDir($book, $scene);
        $sceneJson = json_decode($zip->getFromName("{$sceneDir}/scene.json"), true);

        $this->assertSame($scene->id, $sceneJson['id']);
        $this->assertSame(1, $sceneJson['position']);
        // status is the enum VALUE (machine form), not the human label.
        $this->assertSame('to_edit', $sceneJson['status']);
        $this->assertSame($chapter->id, $sceneJson['chapter_id']);
        $this->assertSame($duringEvent->id, $sceneJson['event_id']);
        $this->assertContains($mentionedEvent->id, $sceneJson['mentioned_event_ids']);

        $zip->close();
    }

    public function test_field_files_hold_the_raw_stored_values_unrendered(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();

        $markdown = "# A heading\n\nParagraph with **bold**.";
        // The description is a stored sanitized HTML fragment (no doctype wrapper).
        $htmlFragment = '<p>Stored <strong>fragment</strong>.</p>';

        $scene = Scene::factory()->for($chapter)->create([
            'contents' => $markdown,
            'description' => $htmlFragment,
        ]);

        // The export must equal the EXACT stored column values, so compare against
        // the values the DB actually holds (description is sanitized on write).
        $scene->refresh();

        $zip = $this->exportZip($user, $project);

        $sceneDir = $this->sceneDir($book, $scene);

        // contents.md === the exact stored Markdown, NOT rendered to HTML.
        $contents = $zip->getFromName("{$sceneDir}/contents.md");
        $this->assertSame($scene->contents, $contents);
        $this->assertStringNotContainsString('<h1>', $contents);

        // description.html === the exact stored fragment, NOT wrapped in a document.
        $description = $zip->getFromName("{$sceneDir}/description.html");
        $this->assertSame($scene->description, $description);
        $this->assertStringNotContainsString('<!doctype', strtolower($description));

        $zip->close();
    }

    public function test_scene_json_excludes_share_link_columns(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();

        $scene = Scene::factory()->for($chapter)->create();
        // Give the scene a live share link — it must never reach the export.
        $scene->forceFill([
            'share_token' => 'secret-token-value',
            'share_expires_at' => now()->addWeek(),
        ])->save();

        $zip = $this->exportZip($user, $project);

        $sceneJson = json_decode($zip->getFromName($this->sceneDir($book, $scene).'/scene.json'), true);
        $this->assertArrayNotHasKey('share_token', $sceneJson);
        $this->assertArrayNotHasKey('share_expires_at', $sceneJson);

        $zip->close();
    }

    public function test_null_content_fields_omit_the_file_and_the_link_key(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();

        $scene = Scene::factory()->for($chapter)->create(['notes' => null]);

        $zip = $this->exportZip($user, $project);

        $sceneDir = $this->sceneDir($book, $scene);
        $this->assertFalse($zip->getFromName("{$sceneDir}/notes.html"), 'notes.html should be omitted for a null notes field.');

        $sceneJson = json_decode($zip->getFromName("{$sceneDir}/scene.json"), true);
        $this->assertArrayNotHasKey('notes_file', $sceneJson);

        $zip->close();
    }

    public function test_an_empty_project_still_exports_project_json_and_no_acts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();

        $zip = $this->exportZip($user, $project);

        $this->assertNotFalse($zip->getFromName('data/project/project.json'));

        // The auto-created book itself is still exported...
        $this->assertNotFalse($zip->getFromName($this->bookDir($book).'/book.json'));

        // ...but no act directories exist for a book with no acts.
        $hasActEntry = false;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            if (str_contains($zip->statIndex($index)['name'], '/acts/')) {
                $hasActEntry = true;
                break;
            }
        }
        $this->assertFalse($hasActEntry, 'An empty book should produce no acts/* entries.');

        $zip->close();
    }

    // ---------------------------------------------------------------------
    // data/books/ branch: book metadata, front/back matter, cover, config
    // ---------------------------------------------------------------------

    public function test_an_unnamed_book_writes_name_as_null(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $this->assertNull($book->name, 'the auto-created first book must start unnamed');

        $zip = $this->exportZip($user, $project);

        $bookJson = json_decode($zip->getFromName($this->bookDir($book).'/book.json'), true);
        $this->assertArrayHasKey('name', $bookJson);
        $this->assertNull($bookJson['name']);

        $zip->close();
    }

    public function test_book_json_carries_every_metadata_field(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        $book->update([
            'name' => 'The Long Winter',
            'language' => BookLanguage::French,
            'author' => 'Jane Doe',
            'publisher' => 'Acme Press',
            'isbn' => '978-3-16-148410-0',
            'overview_render_mode' => StoryOverviewMode::Whole,
            'rights' => 'All rights reserved.',
        ]);

        $zip = $this->exportZip($user, $project);

        $bookDir = $this->bookDir($book);
        $bookJson = json_decode($zip->getFromName("{$bookDir}/book.json"), true);

        $this->assertSame($book->id, $bookJson['id']);
        $this->assertSame('The Long Winter', $bookJson['name']);
        $this->assertSame(1, $bookJson['position']);
        $this->assertSame($project->id, $bookJson['project_id']);
        $this->assertSame('fr', $bookJson['language']);
        $this->assertSame('Jane Doe', $bookJson['author']);
        $this->assertSame('Acme Press', $bookJson['publisher']);
        $this->assertSame('978-3-16-148410-0', $bookJson['isbn']);
        $this->assertSame('whole', $bookJson['overview_render_mode']);

        // rights is plain text, not rich HTML — written as .txt, never .html.
        $this->assertSame('rights.txt', $bookJson['rights_file']);
        $this->assertSame('All rights reserved.', $zip->getFromName("{$bookDir}/rights.txt"));

        $zip->close();
    }

    public function test_book_cover_is_written_with_bytes_when_media_included(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();

        // store() names the file with a random hash, not 'book-cover.jpg' —
        // the exporter uses that stored basename verbatim (basename-guarded).
        $coverPath = UploadedFile::fake()->image('book-cover.jpg', 20, 20)->store('book-covers', 'public');
        $book->update(['cover_image' => $coverPath]);

        $zip = $this->exportZipWithMedia($user, $project);

        $bookDir = $this->bookDir($book);
        $bookJson = json_decode($zip->getFromName("{$bookDir}/book.json"), true);
        $this->assertSame('cover/'.basename($coverPath), $bookJson['cover_file']);
        $this->assertSame(
            Storage::disk('public')->get($coverPath),
            $zip->getFromName("{$bookDir}/cover/".basename($coverPath))
        );

        $zip->close();
    }

    public function test_project_cover_is_written_with_bytes_when_media_included(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $coverPath = UploadedFile::fake()->image('project-cover.jpg', 20, 20)->store('project-covers', 'public');
        $project->update(['cover_image' => $coverPath]);

        $zip = $this->exportZipWithMedia($user, $project);

        $projectJson = json_decode($zip->getFromName('data/project/project.json'), true);
        $this->assertSame('cover/'.basename($coverPath), $projectJson['cover_file']);
        $this->assertSame(
            Storage::disk('public')->get($coverPath),
            $zip->getFromName('data/project/cover/'.basename($coverPath))
        );

        $zip->close();
    }

    public function test_publication_setting_is_written_inside_the_book_folder(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        PublicationSetting::factory()->for($book)->create(['include_book_cover' => false]);

        $zip = $this->exportZip($user, $project);

        $bookDir = $this->bookDir($book);
        $setting = json_decode($zip->getFromName("{$bookDir}/publication-setting.json"), true);
        $this->assertNotNull($setting, "{$bookDir}/publication-setting.json is missing from the export.");
        $this->assertFalse($setting['include_book_cover']);

        // The old project-root location is gone.
        $this->assertFalse($zip->getFromName('data/publication-setting.json'));

        $zip->close();
    }

    public function test_a_two_book_project_exports_both_books_in_position_order(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'The Saga']);
        $firstBook = $project->books()->first();

        $secondBook = Book::factory()->for($project)->create(['name' => 'Volume Two']);
        $firstBook->refresh(); // Book::created renamed it to the project's name.

        $firstAct = Act::factory()->for($firstBook)->create(['name' => 'Book One Act', 'position' => 1]);
        $secondAct = Act::factory()->for($secondBook)->create(['name' => 'Book Two Act', 'position' => 1]);

        $zip = $this->exportZip($user, $project);

        $firstJson = json_decode($zip->getFromName($this->bookDir($firstBook).'/book.json'), true);
        $secondJson = json_decode($zip->getFromName($this->bookDir($secondBook).'/book.json'), true);

        $this->assertSame(1, $firstJson['position']);
        $this->assertSame('The Saga', $firstJson['name']);
        $this->assertSame(2, $secondJson['position']);
        $this->assertSame('Volume Two', $secondJson['name']);

        // Each book's own act tree lives under its own book directory.
        $this->assertNotFalse($zip->getFromName(
            $this->bookDir($firstBook)."/acts/{$firstAct->id}-book-one-act/act.json"
        ));
        $this->assertNotFalse($zip->getFromName(
            $this->bookDir($secondBook)."/acts/{$secondAct->id}-book-two-act/act.json"
        ));

        $zip->close();
    }

    // ---------------------------------------------------------------------
    // data/ Timeline branch: plotlines + events (incl. anchors)
    // ---------------------------------------------------------------------

    public function test_plotlines_are_written_as_entity_directories_with_color_and_is_main(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // A custom plotline alongside the auto-created is_main "Main plotline".
        $custom = Plotline::factory()->for($project)->create([
            'name' => 'Side Quest',
            'color' => '#3b82f6',
            'is_main' => false,
        ]);
        $main = $project->plotlines()->where('is_main', true)->firstOrFail();

        $zip = $this->exportZip($user, $project);

        $customJson = json_decode(
            $zip->getFromName("data/timeline/plotlines/{$custom->id}-side-quest/plotline.json"),
            true
        );
        $this->assertSame($custom->id, $customJson['id']);
        $this->assertSame('Side Quest', $customJson['name']);
        $this->assertSame('#3b82f6', $customJson['color']);
        $this->assertFalse($customJson['is_main']);
        $this->assertSame($project->id, $customJson['project_id']);

        // The auto-created main plotline is exported like any other row, is_main true.
        $mainJson = json_decode(
            $zip->getFromName('data/timeline/plotlines/'.$main->id.'-'.Str::slug($main->name).'/plotline.json'),
            true
        );
        $this->assertTrue($mainJson['is_main']);

        $zip->close();
    }

    public function test_event_json_carries_iso_datetime_and_all_attached_plotline_ids(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $plotlineOne = Plotline::factory()->for($project)->create();
        $plotlineTwo = Plotline::factory()->for($project)->create();

        $event = Event::factory()->for($project)->create([
            'title' => 'The Great Battle',
            'event_datetime' => '2026-05-01 09:30:00',
            'is_fixed' => false,
        ]);
        $event->plotlines()->attach([$plotlineOne->id, $plotlineTwo->id]);

        $zip = $this->exportZip($user, $project);

        $eventJson = json_decode(
            $zip->getFromName("data/timeline/events/{$event->id}-the-great-battle/event.json"),
            true
        );

        $this->assertSame($event->id, $eventJson['id']);
        $this->assertSame('The Great Battle', $eventJson['title']);
        // event_datetime is a stable ISO-8601 string.
        $this->assertSame($event->event_datetime->toIso8601String(), $eventJson['event_datetime']);
        $this->assertFalse($eventJson['is_fixed']);
        $this->assertSame($project->id, $eventJson['project_id']);
        $this->assertContains($plotlineOne->id, $eventJson['plotline_ids']);
        $this->assertContains($plotlineTwo->id, $eventJson['plotline_ids']);

        $zip->close();
    }

    public function test_fixed_bookend_events_are_exported_with_is_fixed_true(): void
    {
        $user = User::factory()->create();
        // A fresh project auto-creates the is_fixed Start/End bookends.
        $project = Project::factory()->for($user)->create();

        $start = $project->startEvent();
        $end = $project->endEvent();

        $zip = $this->exportZip($user, $project);

        foreach ([$start, $end] as $bookend) {
            $json = json_decode(
                $zip->getFromName("data/timeline/events/{$bookend->id}-".Str::slug($bookend->title).'/event.json'),
                true
            );
            $this->assertNotNull($json, "The {$bookend->title} bookend is missing from the export.");
            $this->assertTrue($json['is_fixed'], "The {$bookend->title} bookend should be is_fixed.");
        }

        $zip->close();
    }

    public function test_plotline_and_event_descriptions_are_exported_raw(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $htmlFragment = '<p>A <strong>rich</strong> description.</p>';

        $plotline = Plotline::factory()->for($project)->create(['description' => $htmlFragment]);
        $event = Event::factory()->for($project)->create(['description' => $htmlFragment]);

        // description is sanitized on write; compare against the stored value.
        $plotline->refresh();
        $event->refresh();

        $zip = $this->exportZip($user, $project);

        $plotlineHtml = $zip->getFromName(
            "data/timeline/plotlines/{$plotline->id}-".Str::slug($plotline->name).'/description.html'
        );
        $this->assertSame($plotline->description, $plotlineHtml);
        $this->assertStringNotContainsString('<!doctype', strtolower($plotlineHtml));

        $eventHtml = $zip->getFromName(
            "data/timeline/events/{$event->id}-".Str::slug($event->title).'/description.html'
        );
        $this->assertSame($event->description, $eventHtml);

        $zip->close();
    }

    public function test_a_fresh_project_exports_a_valid_timeline_of_only_anchors(): void
    {
        $user = User::factory()->create();
        // Only the auto-created main plotline + Start/End bookends exist.
        $project = Project::factory()->for($user)->create();

        $zip = $this->exportZip($user, $project);

        $main = $project->plotlines()->where('is_main', true)->firstOrFail();
        $this->assertNotFalse(
            $zip->getFromName('data/timeline/plotlines/'.$main->id.'-'.Str::slug($main->name).'/plotline.json'),
            'The main plotline should be exported for a fresh project.'
        );

        // Both bookend events are present.
        $eventDirCount = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->statIndex($index)['name'];
            if (str_starts_with($name, 'data/timeline/events/') && str_ends_with($name, '/event.json')) {
                $eventDirCount++;
            }
        }
        $this->assertSame(2, $eventDirCount, 'A fresh project should export exactly the two bookend events.');

        $zip->close();
    }

    // ---------------------------------------------------------------------
    // data/ Codex branch: entries, attributes, tags, media + toggle
    // ---------------------------------------------------------------------

    public function test_entry_json_carries_aliases_tags_type_and_raw_description(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $htmlFragment = '<p>A <strong>hero</strong>.</p>';
        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Alice Harker',
            'description' => $htmlFragment,
        ]);
        $entry->aliases()->create(['alias' => 'Ally']);
        $entry->aliases()->create(['alias' => 'The Wanderer']);

        $tag = Tag::factory()->for($project)->create(['name' => 'protagonist']);
        $entry->tags()->attach($tag);

        // description is sanitized on write; compare against the stored value.
        $entry->refresh();

        $zip = $this->exportZip($user, $project);

        $entryDir = $this->codexEntryDir($entry);
        $entryJson = json_decode($zip->getFromName("{$entryDir}/entry.json"), true);

        $this->assertSame($entry->id, $entryJson['id']);
        $this->assertSame('Alice Harker', $entryJson['name']);
        // type is the enum VALUE (machine form), matching the <type> folder.
        $this->assertSame('character', $entryJson['type']);
        $this->assertSame($project->id, $entryJson['project_id']);
        $this->assertEqualsCanonicalizing(['Ally', 'The Wanderer'], $entryJson['aliases']);
        $this->assertSame([$tag->id], $entryJson['tag_ids']);

        // description.html === the exact stored fragment, not re-wrapped/re-rendered.
        $description = $zip->getFromName("{$entryDir}/description.html");
        $this->assertSame($entry->description, $description);
        $this->assertStringNotContainsString('<!doctype', strtolower($description));

        $zip->close();
    }

    public function test_attribute_values_are_anchored_to_events_and_definitions_are_listed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $entry = CodexEntry::factory()->for($project)->character()->create();

        // The attribute DEFINITION (a column on character sheets)...
        $attribute = CodexAttribute::factory()->for($project)
            ->appliesTo(CodexEntryType::Character)
            ->create(['name' => 'Age']);

        // ...and a value anchored to a specific event (the attribute-over-time link).
        $event = Event::factory()->for($project)->create();
        $value = CodexAttributeValue::factory()->create([
            'codex_entry_id' => $entry->id,
            'codex_attribute_id' => $attribute->id,
            'start_event_id' => $event->id,
            'value' => '29',
        ]);

        $zip = $this->exportZip($user, $project);

        $entryJson = json_decode($zip->getFromName($this->codexEntryDir($entry).'/entry.json'), true);

        // The crucial shape: { id, attribute_id, start_event_id, value } anchored to the event.
        $this->assertContains(
            [
                'id' => $value->id,
                'attribute_id' => $attribute->id,
                'start_event_id' => $event->id,
                'value' => '29',
            ],
            $entryJson['attribute_values']
        );

        // The definition is listed flat in data/codex/attributes.json with applies_to + position.
        $attributes = json_decode($zip->getFromName('data/codex/attributes.json'), true);
        $definition = collect($attributes)->firstWhere('id', $attribute->id);
        $this->assertNotNull($definition, 'The attribute definition is missing from attributes.json.');
        $this->assertSame('Age', $definition['name']);
        $this->assertSame(['character'], $definition['applies_to']);
        $this->assertSame($attribute->position, $definition['position']);

        $zip->close();
    }

    public function test_tags_list_matches_the_entry_tag_ids(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $entry = CodexEntry::factory()->for($project)->create();
        $tag = Tag::factory()->for($project)->create(['name' => 'antagonist']);
        $entry->tags()->attach($tag);

        $zip = $this->exportZip($user, $project);

        $tags = json_decode($zip->getFromName('data/tags.json'), true);
        $this->assertContains(['id' => $tag->id, 'name' => 'antagonist'], $tags);

        // The id in tags.json is the same id the entry references.
        $entryJson = json_decode($zip->getFromName($this->codexEntryDir($entry).'/entry.json'), true);
        $this->assertContains($tag->id, $entryJson['tag_ids']);

        $zip->close();
    }

    public function test_entries_are_grouped_by_type_in_the_directory_path(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $character = CodexEntry::factory()->for($project)->character()->create(['name' => 'Alice']);
        $location = CodexEntry::factory()->for($project)->location()->create(['name' => 'The Keep']);

        $zip = $this->exportZip($user, $project);

        $this->assertNotFalse(
            $zip->getFromName("data/codex/character/{$character->id}-alice/entry.json"),
            'The character entry should live under data/codex/character/.'
        );
        $this->assertNotFalse(
            $zip->getFromName("data/codex/location/{$location->id}-the-keep/entry.json"),
            'The location entry should live under data/codex/location/.'
        );

        $zip->close();
    }

    public function test_media_metadata_is_written_but_bytes_are_absent_when_toggle_off(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->create();

        $path = UploadedFile::fake()->image('portrait.jpg', 20, 20)->store('codex-media', 'public');
        $media = CodexMedia::factory()->cover()->for($entry, 'entry')->create([
            'path' => $path,
            'original_name' => 'portrait.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        // Toggle OFF (no include_images in the request).
        $zip = $this->exportZip($user, $project);

        $entryDir = $this->codexEntryDir($entry);
        $entryJson = json_decode($zip->getFromName("{$entryDir}/entry.json"), true);

        // The media[] metadata is present regardless of the toggle.
        $this->assertCount(1, $entryJson['media']);
        $cover = $entryJson['media'][0];
        $this->assertSame($media->id, $cover['id']);
        $this->assertSame('cover', $cover['collection']);
        $this->assertSame('portrait.jpg', $cover['original_name']);
        $this->assertSame('image/jpeg', $cover['mime_type']);
        $this->assertSame('cover/portrait.jpg', $cover['file']);

        // ...but the byte file is NOT in the archive, and no images/manifest.json exists.
        $this->assertFalse(
            $zip->locateName("{$entryDir}/cover/portrait.jpg"),
            'The cover bytes must be absent when the toggle is off.'
        );
        $this->assertNoZipEntryContains($zip, 'images/manifest.json');

        $zip->close();
    }

    public function test_media_bytes_are_copied_for_every_collection_when_toggle_on(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->create();

        $coverPath = UploadedFile::fake()->image('portrait.jpg', 20, 20)->store('codex-media', 'public');
        $imagePath = UploadedFile::fake()->image('sketch.png', 20, 20)->store('codex-media', 'public');
        $filePath = UploadedFile::fake()->create('notes.pdf', 12, 'application/pdf')->store('codex-media', 'public');

        $cover = CodexMedia::factory()->cover()->for($entry, 'entry')
            ->create(['path' => $coverPath, 'original_name' => 'portrait.jpg', 'mime_type' => 'image/jpeg']);
        $image = CodexMedia::factory()->referenceImage()->for($entry, 'entry')
            ->create(['path' => $imagePath, 'original_name' => 'sketch.png', 'mime_type' => 'image/png']);
        $document = CodexMedia::factory()->referenceFile()->for($entry, 'entry')
            ->create(['path' => $filePath, 'original_name' => 'notes.pdf', 'mime_type' => 'application/pdf']);

        // Toggle ON.
        $zip = $this->exportZipWithMedia($user, $project);

        $entryDir = $this->codexEntryDir($entry);

        // Each collection's bytes land at their `file` path and equal the stored bytes verbatim.
        $this->assertSame(
            Storage::disk('public')->get($coverPath),
            $zip->getFromName("{$entryDir}/cover/portrait.jpg")
        );
        $this->assertSame(
            Storage::disk('public')->get($imagePath),
            $zip->getFromName(sprintf('%s/reference-images/%02d-sketch.png', $entryDir, $image->position))
        );
        // A non-image reference file is included too.
        $this->assertSame(
            Storage::disk('public')->get($filePath),
            $zip->getFromName(sprintf('%s/reference-files/%02d-notes.pdf', $entryDir, $document->position))
        );

        // The manifest still lists all three.
        $entryJson = json_decode($zip->getFromName("{$entryDir}/entry.json"), true);
        $this->assertCount(3, $entryJson['media']);

        $zip->close();
    }

    public function test_a_project_with_no_codex_still_emits_the_flat_lists_and_no_entry_dirs(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $zip = $this->exportZip($user, $project);

        // The flat lists are always emitted (empty arrays for a bare project).
        $this->assertSame([], json_decode($zip->getFromName('data/codex/attributes.json'), true));
        $this->assertSame([], json_decode($zip->getFromName('data/tags.json'), true));

        // No entry directories exist.
        $this->assertNoZipEntryContains($zip, 'data/codex/character/');

        $zip->close();
    }

    // ---------------------------------------------------------------------
    // books/ reading layer: TOC + compiled chapter pages + prev/next
    // ---------------------------------------------------------------------

    public function test_book_index_lists_acts_and_links_every_chapter_file(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $actOne = Act::factory()->for($book)->create(['name' => 'The Beginning', 'position' => 1]);
        Chapter::factory()->for($actOne)->create(['name' => 'Opening Chapter', 'position' => 1]);
        Chapter::factory()->for($actOne)->create(['name' => 'Rising Action', 'position' => 2]);

        $actTwo = Act::factory()->for($book)->create(['name' => 'The Reckoning', 'position' => 2]);
        Chapter::factory()->for($actTwo)->create(['name' => 'The Climax', 'position' => 1]);

        $zip = $this->exportZip($user, $project);

        $index = $zip->getFromName('books/01/index.html');
        $this->assertNotFalse($index, 'books/01/index.html is missing from the export.');

        // Every act title appears as a TOC heading...
        $this->assertStringContainsString('The Beginning', $index);
        $this->assertStringContainsString('The Reckoning', $index);

        // ...and every chapter is linked to its compiled page (per-act, zero-padded).
        $this->assertStringContainsString('href="01/01.html"', $index);
        $this->assertStringContainsString('href="01/02.html"', $index);
        $this->assertStringContainsString('href="02/01.html"', $index);
        $this->assertStringContainsString('Opening Chapter', $index);
        $this->assertStringContainsString('The Climax', $index);

        $zip->close();
    }

    public function test_chapter_page_renders_scene_contents_as_html_joined_by_hr_without_titles(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $act = Act::factory()->for($book)->create(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'A Compiled Chapter', 'position' => 1]);

        Scene::factory()->for($chapter)->create([
            'name' => 'SCENEONETITLE',
            'position' => 1,
            'contents' => 'Prose with **bold** words.',
        ]);
        Scene::factory()->for($chapter)->create([
            'name' => 'SCENETWOTITLE',
            'position' => 2,
            'contents' => 'More with *italic* words.',
        ]);

        $zip = $this->exportZip($user, $project);

        $page = $zip->getFromName('books/01/01/01.html');
        $this->assertNotFalse($page, 'books/01/01/01.html is missing from the export.');

        // The chapter title is present.
        $this->assertStringContainsString('A Compiled Chapter', $page);

        // Scene contents are RENDERED Markdown → HTML (unlike the raw data/ layer).
        $this->assertStringContainsString('<strong>bold</strong>', $page);
        $this->assertStringContainsString('<em>italic</em>', $page);

        // The two scenes are joined by an <hr> and carry NO scene titles.
        $this->assertStringContainsString('<hr>', $page);
        $this->assertStringNotContainsString('SCENEONETITLE', $page);
        $this->assertStringNotContainsString('SCENETWOTITLE', $page);

        $zip->close();
    }

    // ---------------------------------------------------------------------
    // Continuous numbering in the books/ layer
    // ---------------------------------------------------------------------

    public function test_book_index_shows_continuous_chapter_numbers_across_the_act_boundary(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $actOne = Act::factory()->for($book)->create(['name' => 'The Beginning', 'position' => 1]);
        Chapter::factory()->for($actOne)->create(['name' => 'Opening Chapter', 'position' => 1]);
        Chapter::factory()->for($actOne)->create(['name' => 'Rising Action', 'position' => 2]);

        $actTwo = Act::factory()->for($book)->create(['name' => 'The Reckoning', 'position' => 2]);
        Chapter::factory()->for($actTwo)->create(['name' => 'The Climax', 'position' => 1]);

        $zip = $this->exportZip($user, $project);

        $index = $zip->getFromName('books/01/index.html');

        // Default format is "Chapter N: Name" — chapter numbers run 1..3 straight
        // through the act boundary, never resetting to 1 for act two.
        $this->assertStringContainsString('Chapter 1: Opening Chapter', $index);
        $this->assertStringContainsString('Chapter 2: Rising Action', $index);
        $this->assertStringContainsString('Chapter 3: The Climax', $index);

        // Acts number too.
        $this->assertStringContainsString('Act 1: The Beginning', $index);
        $this->assertStringContainsString('Act 2: The Reckoning', $index);

        $zip->close();
    }

    public function test_chapter_page_heading_carries_the_continuous_number(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $actOne = Act::factory()->for($book)->create(['position' => 1]);
        Chapter::factory()->for($actOne)->create(['name' => 'First', 'position' => 1]);

        $actTwo = Act::factory()->for($book)->create(['position' => 2]);
        $chapter = Chapter::factory()->for($actTwo)->create(['name' => 'Second Act, Third Chapter', 'position' => 1]);
        Scene::factory()->for($chapter)->create(['position' => 1, 'contents' => 'Prose.']);

        $zip = $this->exportZip($user, $project);

        // Act two's only chapter is still the project's SECOND chapter overall, even
        // though its file lives at books/01/02/01.html (per-act file identity).
        $page = $zip->getFromName('books/01/02/01.html');
        $this->assertStringContainsString('Chapter 2: Second Act, Third Chapter', $page);

        $zip->close();
    }

    /**
     * Every ChapterTitleFormat drives the website's books/ output exactly the way it
     * drives the EPUB — one setting, both exports, checked in both the TOC and the
     * chapter page heading.
     */
    public function test_every_chapter_title_format_drives_the_website_output(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $act = Act::factory()->for($book)->create(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The Storm', 'position' => 1]);
        Scene::factory()->for($chapter)->create(['position' => 1, 'contents' => 'Prose.']);

        $expectations = [
            ChapterTitleFormat::ChapterNumberTitle->value => 'Chapter 1: The Storm',
            ChapterTitleFormat::NumberTitle->value => '1: The Storm',
            ChapterTitleFormat::ChapterNumber->value => 'Chapter 1',
            ChapterTitleFormat::Number->value => '1',
            ChapterTitleFormat::Title->value => 'The Storm',
        ];

        foreach ($expectations as $format => $expected) {
            PublicationSetting::query()->where('book_id', $book->id)->delete();
            PublicationSetting::factory()->for($book)->create(['chapter_title_format' => $format]);

            $zip = $this->exportZip($user, $project);

            $index = $zip->getFromName('books/01/index.html');
            $page = $zip->getFromName('books/01/01/01.html');

            $this->assertStringContainsString($expected, $index, "format [{$format}] did not appear in the TOC.");
            $this->assertStringContainsString($expected, $page, "format [{$format}] did not appear on the chapter page.");

            $zip->close();
        }
    }

    public function test_a_nameless_chapter_under_the_title_format_still_gets_a_toc_label(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $act = Act::factory()->for($book)->create(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => '', 'position' => 1]);
        Scene::factory()->for($chapter)->create(['position' => 1, 'contents' => 'Prose.']);

        PublicationSetting::factory()->for($book)->create(['chapter_title_format' => ChapterTitleFormat::Title]);

        $zip = $this->exportZip($user, $project);

        // The TOC falls back to "Chapter 1" rather than an empty link label.
        $index = $zip->getFromName('books/01/index.html');
        $this->assertStringContainsString('Chapter 1', $index);

        $zip->close();
    }

    public function test_chapter_files_are_numbered_by_zero_padded_act_and_per_act_chapter_position(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $actOne = Act::factory()->for($book)->create(['position' => 1]);
        Chapter::factory()->for($actOne)->create(['position' => 1]);
        Chapter::factory()->for($actOne)->create(['position' => 2]);

        $actTwo = Act::factory()->for($book)->create(['position' => 2]);
        Chapter::factory()->for($actTwo)->create(['position' => 1]);

        $zip = $this->exportZip($user, $project);

        // Per-act numbering: act 2's first chapter is 02/01.html, not a global 03.
        $this->assertNotFalse($zip->getFromName('books/01/01/01.html'));
        $this->assertNotFalse($zip->getFromName('books/01/01/02.html'));
        $this->assertNotFalse($zip->getFromName('books/01/02/01.html'));
        $this->assertFalse($zip->getFromName('books/01/01/03.html'), 'Chapter numbering must reset per act.');

        $zip->close();
    }

    public function test_prev_next_navigation_crosses_act_boundaries_and_ends_link_to_the_toc(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        // Reading order: A (01/01) → B (01/02) → C (02/01), the last hop crossing acts.
        $actOne = Act::factory()->for($book)->create(['position' => 1]);
        Chapter::factory()->for($actOne)->create(['position' => 1]);
        Chapter::factory()->for($actOne)->create(['position' => 2]);

        $actTwo = Act::factory()->for($book)->create(['position' => 2]);
        Chapter::factory()->for($actTwo)->create(['position' => 1]);

        $zip = $this->exportZip($user, $project);

        $first = $zip->getFromName('books/01/01/01.html');
        $middle = $zip->getFromName('books/01/01/02.html');
        $last = $zip->getFromName('books/01/02/01.html');

        // First chapter: prev → the TOC, next → the following chapter.
        $this->assertStringContainsString('href="../index.html"', $first);
        $this->assertStringContainsString('href="../01/02.html"', $first);

        // Middle chapter: prev within the act, next CROSSING into the sibling act folder.
        $this->assertStringContainsString('href="../01/01.html"', $middle);
        $this->assertStringContainsString('href="../02/01.html"', $middle);

        // Last chapter: prev → the previous chapter, next → back to the TOC.
        $this->assertStringContainsString('href="../01/02.html"', $last);
        $this->assertStringContainsString('href="../index.html"', $last);

        // Navigation appears at BOTH top and bottom — each end link renders twice.
        $this->assertSame(2, substr_count($first, 'href="../index.html"'));
        $this->assertSame(2, substr_count($last, 'href="../index.html"'));

        $zip->close();
    }

    public function test_titles_are_html_escaped_in_the_book_layer(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $act = Act::factory()->for($book)->create(['name' => 'Act & <em>Ampersand</em>', 'position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Chapter <b>Bold</b>', 'position' => 1]);
        Scene::factory()->for($chapter)->create(['position' => 1, 'contents' => 'Plain prose.']);

        $zip = $this->exportZip($user, $project);

        // TOC: the act/chapter titles are plain text and must be escaped, never raw HTML.
        $index = $zip->getFromName('books/01/index.html');
        $this->assertStringContainsString('Act &amp; &lt;em&gt;Ampersand&lt;/em&gt;', $index);
        $this->assertStringContainsString('Chapter &lt;b&gt;Bold&lt;/b&gt;', $index);
        $this->assertStringNotContainsString('<em>Ampersand</em>', $index);
        $this->assertStringNotContainsString('<b>Bold</b>', $index);

        // Chapter page heading escapes the title too.
        $page = $zip->getFromName('books/01/01/01.html');
        $this->assertStringContainsString('Chapter &lt;b&gt;Bold&lt;/b&gt;', $page);
        $this->assertStringNotContainsString('<b>Bold</b>', $page);

        $zip->close();
    }

    public function test_an_empty_project_still_exports_a_books_index_with_no_chapter_files(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $zip = $this->exportZip($user, $project);

        // The top-level books/ index and this book's own TOC both render even
        // with nothing in them, no error.
        $this->assertNotFalse($zip->getFromName('books/index.html'));
        $this->assertNotFalse($zip->getFromName('books/01/index.html'));

        // No compiled chapter pages exist for a book with no chapters.
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->statIndex($index)['name'];
            if (str_starts_with($name, 'books/01/') && $name !== 'books/01/index.html') {
                $this->fail("An empty book should produce no books/01/ pages besides index.html; found {$name}.");
            }
        }

        $zip->close();
    }

    public function test_books_index_links_every_books_own_table_of_contents(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'The Saga']);
        $firstBook = $project->books()->first();

        $secondBook = Book::factory()->for($project)->create(['name' => 'Volume Two']);
        $firstBook->refresh(); // Book::created renamed it to the project's name.

        $zip = $this->exportZip($user, $project);

        $index = $zip->getFromName('books/index.html');
        $this->assertNotFalse($index, 'books/index.html is missing from the export.');

        $this->assertStringContainsString('The Saga', $index);
        $this->assertStringContainsString('Volume Two', $index);
        $this->assertStringContainsString('href="01/index.html"', $index);
        $this->assertStringContainsString('href="02/index.html"', $index);

        $zip->close();
    }

    // ---------------------------------------------------------------------
    // Revision history is never exported
    // ---------------------------------------------------------------------

    public function test_an_export_writes_no_revisions_even_when_the_project_has_revisions(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create();

        Revision::factory()->count(3)->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $project->id,
            'field' => 'contents',
        ]);

        $zip = $this->exportZip($user, $project);

        $manifest = json_decode($zip->getFromName('data/manifest.json'), true);
        $this->assertArrayNotHasKey('includes_revisions', $manifest);
        $this->assertNoZipEntryContains($zip, 'revisions/');

        $zip->close();
    }

    public function test_a_stray_include_revisions_field_is_ignored_and_no_revisions_are_written(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create();

        Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $project->id,
            'field' => 'contents',
        ]);

        $response = $this->actingAs($user)->post(route('admin.data.export'), [
            'project_id' => $project->id,
            'include_revisions' => '1',
        ]);

        $response->assertOk();

        $zip = $this->openExport($response);
        $this->assertNoZipEntryContains($zip, 'revisions/');
        $zip->close();
    }

    /**
     * EPUB export has no include_revisions-equivalent option at all (v1 only takes
     * book_id, per EpubExportRequest's own docblock) — a regression guard that
     * this toggle never leaks into that separate exporter.
     */
    public function test_epub_export_has_no_include_revisions_option(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        Scene::factory()->for($chapter)->create();

        $response = $this->actingAs($user)->post(route('admin.data.export.epub'), [
            'book_id' => $book->id,
            'include_revisions' => '1',
        ]);

        // The extra field is simply ignored, not rejected — but proves EPUB export
        // never reads/acts on it.
        $response->assertOk();
        $response->assertHeader('content-type', 'application/epub+zip');
    }

    /**
     * Export the project with the "Include images & files" toggle ON and return the
     * opened zip. Mirrors exportZip() but sends include_images.
     */
    private function exportZipWithMedia(User $user, Project $project): ZipArchive
    {
        $response = $this->actingAs($user)->post(route('admin.data.export'), [
            'project_id' => $project->id,
            'include_images' => '1',
        ]);

        $response->assertOk();

        return $this->openExport($response);
    }

    /**
     * The data/ directory path of a Codex entry, resolved via the same `<type>/<id>-slug`
     * scheme the exporter uses.
     */
    private function codexEntryDir(CodexEntry $entry): string
    {
        return 'data/codex/'.$entry->type->value.'/'.$entry->id.'-'.Str::slug($entry->name);
    }

    /**
     * Assert no entry name in the zip contains the given needle.
     */
    private function assertNoZipEntryContains(ZipArchive $zip, string $needle): void
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $this->assertStringNotContainsString(
                $needle,
                $zip->statIndex($index)['name'],
                "Unexpected zip entry containing '{$needle}'."
            );
        }
    }

    /**
     * The data/ directory path of a scene, resolved via the same `<id>-slug` scheme
     * the exporter uses.
     */
    private function sceneDir(Book $book, Scene $scene): string
    {
        $chapter = $scene->chapter;
        $act = $chapter->act;

        return $this->bookDir($book).'/acts/'.$act->id.'-'.Str::slug($act->name)
            .'/chapters/'.$chapter->id.'-'.Str::slug($chapter->name)
            .'/scenes/'.$scene->id.'-'.Str::slug($scene->name);
    }

    /**
     * The data/books/ directory path of a book, resolved via the same
     * `<id>-slug` scheme the exporter uses. Computed off
     * {@see Book::displayName()}, never `->name` directly — an unnamed book
     * slugs its project's name.
     */
    private function bookDir(Book $book): string
    {
        return 'data/books/'.$book->id.'-'.Str::slug($book->displayName());
    }
}
