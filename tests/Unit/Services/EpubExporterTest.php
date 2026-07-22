<?php

namespace Tests\Unit\Services;

use App\Exceptions\EpubExportException;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Project;
use App\Models\PublicationSetting;
use App\Models\Scene;
use App\Services\CoverImageService;
use App\Services\EpubExporter;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Service-level tests for EpubExporter's content-generation stage (task 03): the
 * skip-empty filtering, position ordering, Act/Chapter page content, the epub-only
 * SmartPunct typography, and the `lang` attribute. Packaging (04) and structural
 * validation (05) are not exercised here.
 *
 * Uses RefreshDatabase + factories because the service reads the persisted
 * act → chapter → scene tree, but asserts directly on the service output rather than
 * through the HTTP layer.
 */
class EpubExporterTest extends TestCase
{
    use RefreshDatabase;

    private function exporter(): EpubExporter
    {
        return new EpubExporter(app(CoverImageService::class));
    }

    /**
     * Read the package document (book.opf) out of a generated .epub.
     */
    private function opfOf(string $path): string
    {
        $opf = $this->entryOf($path, 'OEBPS/book.opf');
        $this->assertNotFalse($opf, 'the epub must contain an OPF package document');

        return $opf;
    }

    /**
     * Read a single named entry out of a generated .epub zip (false if absent).
     */
    private function entryOf(string $path, string $entry): string|false
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, "the generated epub at {$path} must be a readable zip");
        $contents = $zip->getFromName($entry);
        $zip->close();

        return $contents;
    }

    /**
     * Whether the epub contains a zip entry whose name ends with the given suffix.
     */
    private function epubHasEntryEndingWith(string $path, string $suffix): bool
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with((string) $zip->getNameIndex($i), $suffix)) {
                    return true;
                }
            }
        } finally {
            $zip->close();
        }

        return false;
    }

    /**
     * Assert two generated .epub packages have byte-for-byte identical content, treating
     * only the OPF's publication-timestamp lines (dc:date / dcterms:modified) as
     * insignificant. Those are the sole values the epub library derives from time() at
     * finalize, so two separate export() calls can legitimately differ there by a second
     * without any content having changed. Every other entry — content documents, nav, CSS,
     * cover, the rest of the OPF — must match exactly.
     */
    private function assertContentIdenticalIgnoringOpfTimestamp(string $pathA, string $pathB, string $message): void
    {
        $zipA = new ZipArchive;
        $zipB = new ZipArchive;
        $this->assertTrue($zipA->open($pathA) === true);
        $this->assertTrue($zipB->open($pathB) === true);

        try {
            $this->assertSame($zipA->numFiles, $zipB->numFiles, "{$message} (entry count)");

            for ($i = 0; $i < $zipA->numFiles; $i++) {
                $name = (string) $zipA->getNameIndex($i);
                $contentA = (string) $zipA->getFromName($name);
                $contentB = (string) $zipB->getFromName($name);

                if (str_ends_with($name, '.opf')) {
                    $contentA = $this->stripOpfTimestamps($contentA);
                    $contentB = $this->stripOpfTimestamps($contentB);
                }

                $this->assertSame($contentA, $contentB, "{$message} (entry {$name})");
            }
        } finally {
            $zipA->close();
            $zipB->close();
        }
    }

    /**
     * Blank out the two time()-derived OPF timestamp values so a content comparison is
     * immune to the export-clock drift described on
     * {@see assertContentIdenticalIgnoringOpfTimestamp()}.
     */
    private function stripOpfTimestamps(string $opf): string
    {
        $opf = preg_replace('#<dc:date>.*?</dc:date>#', '<dc:date/>', $opf);

        return preg_replace(
            '#<meta property="dcterms:modified">.*?</meta>#',
            '<meta property="dcterms:modified"/>',
            $opf
        );
    }

    public function test_it_drops_chapters_with_no_scenes(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();

        $withScenes = Chapter::factory()->for($act)->create();
        Scene::factory()->for($withScenes)->create();

        Chapter::factory()->for($act)->create(); // empty chapter, no scenes

        $tree = $this->exporter()->filteredTree($project);

        $this->assertCount(1, $tree);
        $this->assertCount(1, $tree->first()->chapters);
        $this->assertTrue($tree->first()->chapters->first()->is($withScenes));
    }

    public function test_it_drops_acts_left_with_no_surviving_chapters(): void
    {
        $project = Project::factory()->create();

        // Act 1 keeps a chapter with a scene.
        $keptAct = Act::factory()->for($project)->create();
        $keptChapter = Chapter::factory()->for($keptAct)->create();
        Scene::factory()->for($keptChapter)->create();

        // Act 2's only chapter has zero scenes → the whole act must disappear.
        $emptyAct = Act::factory()->for($project)->create();
        Chapter::factory()->for($emptyAct)->create();

        $tree = $this->exporter()->filteredTree($project);

        $this->assertCount(1, $tree);
        $this->assertTrue($tree->first()->is($keptAct));
    }

    public function test_the_filtered_tree_is_position_ordered_at_every_level(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        // Create scenes out of position order, then force positions 3, 1, 2.
        $third = Scene::factory()->for($chapter)->create(['contents' => 'gamma']);
        $first = Scene::factory()->for($chapter)->create(['contents' => 'alpha']);
        $second = Scene::factory()->for($chapter)->create(['contents' => 'beta']);
        $third->update(['position' => 3]);
        $first->update(['position' => 1]);
        $second->update(['position' => 2]);

        // A second act with a lower position created afterwards must still sort first.
        $laterButFirst = Act::factory()->for($project)->create();
        $laterButFirst->update(['position' => 0]);
        $chapterOfFirst = Chapter::factory()->for($laterButFirst)->create();
        Scene::factory()->for($chapterOfFirst)->create();

        $tree = $this->exporter()->filteredTree($project);

        $this->assertTrue($tree->first()->is($laterButFirst), 'Acts must sort by position, not insertion.');

        $scenes = $tree->last()->chapters->first()->scenes;
        $this->assertSame([1, 2, 3], $scenes->pluck('position')->all());
        $this->assertSame(['alpha', 'beta', 'gamma'], $scenes->pluck('contents')->all());
    }

    public function test_act_page_renders_number_and_name_but_never_description(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create([
            'name' => 'The Gathering Storm',
            'description' => 'SECRET_DESCRIPTION',
        ]);
        $act->update(['position' => 2]);

        $html = $this->exporter()->renderAct($act, $project);

        $this->assertStringContainsString('Act 2', $html);
        $this->assertStringContainsString('The Gathering Storm', $html);
        $this->assertStringNotContainsString('SECRET_DESCRIPTION', $html);
    }

    public function test_act_page_with_blank_name_renders_number_only(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create(['name' => '']);
        $act->update(['position' => 1]);

        $html = $this->exporter()->renderAct($act, $project);

        $this->assertStringContainsString('Act 1', $html);
        // No empty name paragraph when the name is blank.
        $this->assertStringNotContainsString('class="act-name"', $html);
    }

    public function test_chapter_page_renders_hr_joined_scenes_without_titles_or_description(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create([
            'name' => 'A Long Expected Party',
            'description' => 'SECRET_DESCRIPTION',
        ]);
        $chapter->update(['position' => 3]);

        Scene::factory()->for($chapter)->create(['name' => 'SCENE_ONE_TITLE', 'contents' => 'First scene prose.']);
        Scene::factory()->for($chapter)->create(['name' => 'SCENE_TWO_TITLE', 'contents' => 'Second scene prose.']);

        $tree = $this->exporter()->filteredTree($project);
        $renderedChapter = $tree->first()->chapters->first();

        $html = $this->exporter()->renderChapter($renderedChapter, $project);

        $this->assertStringContainsString('Chapter 3: A Long Expected Party', $html);
        $this->assertStringContainsString('First scene prose.', $html);
        $this->assertStringContainsString('Second scene prose.', $html);
        $this->assertStringContainsString('<hr/>', $html);
        $this->assertStringNotContainsString('SECRET_DESCRIPTION', $html);
        $this->assertStringNotContainsString('SCENE_ONE_TITLE', $html);
        $this->assertStringNotContainsString('SCENE_TWO_TITLE', $html);
    }

    public function test_typography_is_smart_in_the_epub_but_scene_rendered_contents_is_unaffected(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create([
            'contents' => 'A dash -- and a range --- and an ellipsis... and "quotes".',
        ]);

        $tree = $this->exporter()->filteredTree($project);
        $html = $this->exporter()->renderChapter($tree->first()->chapters->first(), $project);

        // Epub output: SmartPunct converts dashes, ellipsis, and straight quotes.
        $this->assertStringContainsString("\u{2013}", $html, 'en-dash expected for --');
        $this->assertStringContainsString("\u{2014}", $html, 'em-dash expected for ---');
        $this->assertStringContainsString("\u{2026}", $html, 'ellipsis expected for ...');
        $this->assertStringContainsString("\u{201C}", $html, 'opening curly quote expected');
        $this->assertStringContainsString("\u{201D}", $html, 'closing curly quote expected');

        // Isolation: the shared accessor must remain the raw, straight-punctuation render.
        $shared = $scene->fresh()->renderedContents;
        $this->assertStringContainsString('--', $shared);
        $this->assertStringContainsString('...', $shared);
        // Straight quotes stay straight (HTML-escaped to &quot;), never curled.
        $this->assertStringContainsString('&quot;quotes&quot;', $shared);
        $this->assertStringNotContainsString("\u{2014}", $shared, 'shared render must not get em-dashes');
        $this->assertStringNotContainsString("\u{201C}", $shared, 'shared render must not get curly quotes');
    }

    public function test_strikethrough_and_task_list_render_as_real_markup_in_the_epub(): void
    {
        // Task 02 (expand-tip-tap): the isolated EPUB converter gets Strikethrough/
        // TaskList extensions added alongside SmartPunct, so this markup renders as
        // real HTML instead of literal tildes/brackets — matching what the editor and
        // Scene::renderedContents() already produce via GFM.
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create([
            'contents' => "This is ~~struck~~ text.\n\n- [ ] todo\n- [x] done",
        ]);

        $tree = $this->exporter()->filteredTree($project);
        $html = $this->exporter()->renderChapter($tree->first()->chapters->first(), $project);

        $this->assertStringContainsString('<del>struck</del>', $html, 'strikethrough must render as <del>, not literal tildes');
        $this->assertStringNotContainsString('~~', $html);

        $this->assertStringContainsString('type="checkbox"', $html, 'task list items must render as real checkboxes');
        $this->assertStringContainsString('checked', $html, 'the checked item must render its checked state');
        $this->assertStringNotContainsString('[ ] todo', $html, 'unchecked item must not render as literal brackets');
        $this->assertStringNotContainsString('[x] done', $html, 'checked item must not render as literal brackets');
    }

    public function test_full_metadata_epub_opf_contains_every_field_and_both_identifiers(): void
    {
        Storage::fake('public');
        // A tiny but valid PNG so the cover embed reads real bytes off the public disk.
        Storage::disk('public')->put('project-covers/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        ));

        $project = Project::factory()->create([
            'name' => 'The Whole Manuscript',
            'language' => 'fr',
            'author' => 'Jane Author',
            'publisher' => 'Imago Press',
            'rights' => 'Copyright 2026 Jane Author',
            'isbn' => '978-0-306-40615-7',
            'cover_image' => 'project-covers/cover.png',
        ]);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);

        $opf = $this->opfOf($path);

        $this->assertStringContainsString('<dc:title>The Whole Manuscript</dc:title>', $opf);
        $this->assertStringContainsString('<dc:language>fr</dc:language>', $opf);
        $this->assertStringContainsString('Jane Author', $opf, 'dc:creator expected');
        $this->assertStringContainsString('<dc:creator', $opf);
        $this->assertStringContainsString('<dc:publisher>Imago Press</dc:publisher>', $opf);
        $this->assertStringContainsString('<dc:rights>Copyright 2026 Jane Author</dc:rights>', $opf);

        // Both identifiers: the always-present generated URN AND the ISBN as a second one.
        $this->assertStringContainsString("urn:imagoldfish:project:{$project->id}", $opf);
        $this->assertStringContainsString('urn:isbn:978-0-306-40615-7', $opf);
        $this->assertSame(2, substr_count($opf, '<dc:identifier'), 'both identifiers must be present');

        // Accessibility metadata via the library's native methods.
        $this->assertStringContainsString('schema:accessibilitySummary', $opf);
        $this->assertStringContainsString('schema:accessMode', $opf);
        $this->assertStringContainsString('schema:accessibilityFeature', $opf);

        // Cover image embedded in the manifest and referenced as the cover.
        $this->assertStringContainsString('<meta name="cover" content="CoverImage"', $opf);
        $this->assertStringContainsString('cover.png', $opf);
        $this->assertTrue($this->epubHasEntryEndingWith($path, 'cover.png'), 'cover bytes must be packaged');

        @unlink($path);
    }

    public function test_minimal_metadata_epub_opf_omits_optional_fields(): void
    {
        // A plain factory project: language defaults to 'en', every optional field is null.
        $project = Project::factory()->create(['name' => 'Bare Bones']);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);

        $opf = $this->opfOf($path);

        $this->assertStringContainsString('<dc:title>Bare Bones</dc:title>', $opf);
        $this->assertStringContainsString('<dc:language>en</dc:language>', $opf);
        $this->assertStringContainsString("urn:imagoldfish:project:{$project->id}", $opf);

        // Only the generated URN — no ISBN, no other optional Dublin Core fields.
        $this->assertSame(1, substr_count($opf, '<dc:identifier'), 'only the generated URN identifier');
        $this->assertStringNotContainsString('urn:isbn:', $opf);
        $this->assertStringNotContainsString('<dc:creator', $opf);
        $this->assertStringNotContainsString('<dc:publisher', $opf);
        $this->assertStringNotContainsString('<dc:rights', $opf);
        $this->assertStringNotContainsString('<meta name="cover"', $opf);

        // Accessibility metadata is unconditional.
        $this->assertStringContainsString('schema:accessibilitySummary', $opf);

        @unlink($path);
    }

    /**
     * The defaults===v1 regression guard (task 08, overview decision #3): every later
     * exporter task (09-13) threads a new PublicationSetting toggle through the exporter,
     * and every one of those toggles must default to "today's behaviour". This test is the
     * single automated proof of that contract for THIS task's slice (metadata + project
     * cover): it exports the same richly-populated project twice —
     *   (a) with NO PublicationSetting row at all (the lazy default returned by
     *       Project::publicationSettingOrDefault()), and
     *   (b) with an EXPLICIT row whose every column is the literal default value (the
     *       PublicationSettingFactory's default state, which mirrors
     *       publicationSettingOrDefault() field-for-field) —
     * and asserts the two generated .epub files are byte-for-byte identical. Because
     * applyMetadata()/applyCover() are the ONLY methods task 08 touches, and both are now
     * gated behind toggles that default true, a byte-for-byte match here proves the gating
     * is a true no-op for a default project — i.e. defaults reproduce the pre-toggle (v1)
     * output. (A literal diff against a pre-feature commit's output is not something an
     * automated test can pin without a stored binary fixture — this equivalence check is
     * the practical, self-contained proxy: it fails immediately if any future task makes a
     * "default" toggle do something a truly-untouched export would not have done.)
     */
    public function test_defaults_v1_regression_lazy_default_and_explicit_default_row_produce_byte_identical_epubs(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('project-covers/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        ));

        $project = Project::factory()->create([
            'name' => 'The Whole Manuscript',
            'author' => 'Jane Author',
            'publisher' => 'Imago Press',
            'rights' => 'Copyright 2026 Jane Author',
            'isbn' => '978-0-306-40615-7',
            'cover_image' => 'project-covers/cover.png',
        ]);
        $act = Act::factory()->for($project)->create(['name' => 'Act One']);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'A Beginning']);
        Scene::factory()->for($chapter)->create();
        Scene::factory()->for($chapter)->create();

        $lazyPath = $this->exporter()->export($project->fresh());

        // Every column set to the literal default value — PublicationSettingFactory's
        // definition() mirrors Project::publicationSettingOrDefault() field-for-field.
        PublicationSetting::factory()->for($project)->create();

        $explicitPath = $this->exporter()->export($project->fresh());

        // Compare the two packages entry-by-entry rather than as one raw byte stream. The
        // guard's intent is that the lazy-default and explicit-default paths produce
        // identical CONTENT; the only value that legitimately differs between two separate
        // export() calls is the OPF publication timestamp (dc:date / dcterms:modified),
        // which the epub library stamps from time() and which drifts by a second when the
        // two back-to-back exports straddle a wall-clock boundary (common under the
        // parallel test runner). Normalising just those two timestamp lines keeps the
        // byte-for-byte content comparison exact while immunising the guard against that
        // pre-existing race (flagged in task 08's own resolution log).
        $this->assertContentIdenticalIgnoringOpfTimestamp(
            $lazyPath,
            $explicitPath,
            'a project with no PublicationSetting row and one with an explicit all-defaults row must export identical epub content'
        );

        // Content-level sanity check on top of the byte match: today's chapter-heading
        // format, <hr/>-joined scenes with no titles, and metadata present because the
        // columns are set — the exact shape task 09+ must not disturb for a default project.
        $opf = $this->opfOf($explicitPath);
        $this->assertStringContainsString('<dc:creator', $opf);
        $this->assertStringContainsString('<dc:publisher>Imago Press</dc:publisher>', $opf);
        $this->assertStringContainsString('<dc:rights>Copyright 2026 Jane Author</dc:rights>', $opf);
        $this->assertStringContainsString('urn:isbn:978-0-306-40615-7', $opf);
        $this->assertStringContainsString('<meta name="cover" content="CoverImage"', $opf);

        $chapterXhtml = $this->exporter()->renderChapter($chapter->fresh()->load('scenes'), $project);
        $this->assertStringContainsString('Chapter 1: A Beginning', $chapterXhtml);
        $this->assertStringContainsString('<hr', $chapterXhtml);

        @unlink($lazyPath);
        @unlink($explicitPath);
    }

    public function test_include_author_false_omits_dc_creator_but_keeps_other_metadata(): void
    {
        $project = Project::factory()->create([
            'author' => 'Jane Author',
            'publisher' => 'Imago Press',
        ]);
        PublicationSetting::factory()->for($project)->create(['include_author' => false]);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);
        $opf = $this->opfOf($path);

        $this->assertStringNotContainsString('<dc:creator', $opf);
        $this->assertStringContainsString('<dc:publisher>Imago Press</dc:publisher>', $opf, 'publisher stays gated independently');

        @unlink($path);
    }

    public function test_include_publisher_false_omits_dc_publisher(): void
    {
        $project = Project::factory()->create(['publisher' => 'Imago Press']);
        PublicationSetting::factory()->for($project)->create(['include_publisher' => false]);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);
        $opf = $this->opfOf($path);

        $this->assertStringNotContainsString('<dc:publisher', $opf);

        @unlink($path);
    }

    public function test_include_rights_false_omits_dc_rights(): void
    {
        $project = Project::factory()->create(['rights' => 'Copyright 2026 Jane Author']);
        PublicationSetting::factory()->for($project)->create(['include_rights' => false]);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);
        $opf = $this->opfOf($path);

        $this->assertStringNotContainsString('<dc:rights', $opf);

        @unlink($path);
    }

    public function test_include_isbn_false_omits_urn_isbn_identifier_but_keeps_generated_urn(): void
    {
        $project = Project::factory()->create(['isbn' => '978-0-306-40615-7']);
        PublicationSetting::factory()->for($project)->create(['include_isbn' => false]);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);
        $opf = $this->opfOf($path);

        $this->assertStringNotContainsString('urn:isbn:', $opf);
        $this->assertStringContainsString("urn:imagoldfish:project:{$project->id}", $opf, 'the generated URN identifier is unconditional');
        $this->assertSame(1, substr_count($opf, '<dc:identifier'));

        @unlink($path);
    }

    public function test_include_project_cover_false_omits_cover_but_keeps_title_urn_and_accessibility(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('project-covers/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        ));

        $project = Project::factory()->create([
            'name' => 'The Whole Manuscript',
            'cover_image' => 'project-covers/cover.png',
        ]);
        PublicationSetting::factory()->for($project)->create(['include_project_cover' => false]);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);
        $opf = $this->opfOf($path);

        $this->assertStringNotContainsString('<meta name="cover"', $opf);
        $this->assertFalse($this->epubHasEntryEndingWith($path, 'cover.png'), 'cover bytes must not be packaged');

        // Unconditional metadata is unaffected by the toggle.
        $this->assertStringContainsString('<dc:title>The Whole Manuscript</dc:title>', $opf);
        $this->assertStringContainsString("urn:imagoldfish:project:{$project->id}", $opf);
        $this->assertStringContainsString('schema:accessibilitySummary', $opf);

        @unlink($path);
    }

    public function test_toc_nav_is_two_level_with_chapters_nested_under_acts(): void
    {
        $project = Project::factory()->create();

        $act = Act::factory()->for($project)->create(['name' => 'Rising Action']);
        $act->update(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The Beginning']);
        $chapter->update(['position' => 1]);
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);

        $nav = $this->entryOf($path, 'OEBPS/epub3toc.xhtml');
        $this->assertNotFalse($nav, 'an EPUB 3 nav document must be packaged');

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$nav);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $actLink = $xpath->query("//li/a[contains(., 'Act 1')]")->item(0);
        $this->assertNotNull($actLink, 'the Act must be a nav entry');
        $this->assertStringContainsString('Rising Action', $actLink->textContent);

        // The Chapter must live inside a nested <ol> under the Act's <li> — that nesting IS
        // the two-level structure.
        $nestedChapter = $xpath->query(".//ol//a[contains(., 'Chapter 1')]", $actLink->parentNode)->item(0);
        $this->assertNotNull($nestedChapter, 'the Chapter must be nested under its Act');
        $this->assertStringContainsString('The Beginning', $nestedChapter->textContent);

        @unlink($path);
    }

    public function test_front_matter_spine_order_is_title_then_toc_then_story(): void
    {
        $project = Project::factory()->create(['name' => 'The Front Matter Book']);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);

        $opf = $this->opfOf($path);

        $dom = new DOMDocument;
        $dom->loadXML($opf);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('opf', 'http://www.idpf.org/2007/opf');

        // Resolve the spine's itemref order to hrefs via the manifest, so this asserts on
        // actual reading order rather than assuming manifest/spine declaration order match.
        $hrefById = [];
        foreach ($xpath->query('//opf:manifest/opf:item') as $item) {
            $hrefById[$item->getAttribute('id')] = $item->getAttribute('href');
        }

        $spineHrefs = [];
        foreach ($xpath->query('//opf:spine/opf:itemref') as $itemref) {
            $spineHrefs[] = $hrefById[$itemref->getAttribute('idref')] ?? null;
        }

        // Title page first, table of contents second, then the story itself — the exact
        // order EpubExporter::addFrontMatter() adds them, before addNavigation() runs.
        $this->assertSame('title.xhtml', $spineHrefs[0], 'the title page must be the first spine item');
        $this->assertSame('toc.xhtml', $spineHrefs[1], 'the table of contents must follow the title page');
        $this->assertStringStartsWith('act-', (string) $spineHrefs[2], 'the story must follow the front matter');

        @unlink($path);
    }

    public function test_toc_page_links_to_the_correct_act_and_chapter_files(): void
    {
        $project = Project::factory()->create();

        $act = Act::factory()->for($project)->create(['name' => 'Rising Action']);
        $act->update(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The Beginning']);
        $chapter->update(['position' => 1]);
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);

        $toc = $this->entryOf($path, 'OEBPS/toc.xhtml');
        $this->assertNotFalse($toc, 'a toc.xhtml content page must be packaged');

        $dom = new DOMDocument;
        $dom->loadXML((string) $toc);
        // The content documents declare a default xmlns, so plain //a-style queries
        // silently match nothing under DOMXPath; match by local-name() instead of
        // registering/prefixing the namespace.
        $xpath = new DOMXPath($dom);

        $actLink = $xpath->query("//*[local-name()='a'][@href='act-{$act->id}.xhtml']")->item(0);
        $this->assertNotNull($actLink, 'the toc page must link directly to the act file');
        $this->assertSame('Act 1: Rising Action', $actLink->textContent);

        $chapterLink = $xpath->query("//*[local-name()='a'][@href='chapter-{$chapter->id}.xhtml']")->item(0);
        $this->assertNotNull($chapterLink, 'the toc page must link directly to the chapter file');
        $this->assertSame('Chapter 1: The Beginning', $chapterLink->textContent);

        // The chapter link must be nested inside the act's <li>, mirroring the nav nesting.
        $nestedChapter = $xpath->query(
            ".//*[local-name()='ol']//*[local-name()='a'][@href='chapter-{$chapter->id}.xhtml']",
            $actLink->parentNode
        )->item(0);
        $this->assertNotNull($nestedChapter, 'the chapter link must be nested under its act on the toc page');

        @unlink($path);
    }

    public function test_title_page_renders_the_project_name_as_a_centered_larger_heading(): void
    {
        $project = Project::factory()->create(['name' => 'A Very Large Story']);

        $html = $this->exporter()->renderTitlePage($project);

        $dom = new DOMDocument;
        $dom->loadXML($html);
        // See the local-name() note in test_toc_page_links_to_the_correct_act_and_chapter_files.
        $xpath = new DOMXPath($dom);

        $heading = $xpath->query("//*[local-name()='h1'][@class='story-title']")->item(0);
        $this->assertNotNull($heading, 'the title page must have an h1.story-title heading');
        $this->assertSame('A Very Large Story', $heading->textContent);

        // The styling contract lives in styles.css: .title-page is centered and
        // .story-title is set larger — assert the stylesheet actually declares both, since
        // that is what the reader will apply to the classes rendered above.
        $stylesheet = $this->exporter()->stylesheet();
        $this->assertMatchesRegularExpression(
            '/section\.title-page[^{]*\{[^}]*text-align:\s*center/s',
            $stylesheet,
            'the title page must be centered'
        );
        $this->assertMatchesRegularExpression(
            '/section\.title-page \.story-title\s*\{[^}]*font-size/s',
            $stylesheet,
            'the story title must be set larger'
        );
    }

    public function test_act_headings_are_centered_and_larger_in_the_stylesheet(): void
    {
        $stylesheet = $this->exporter()->stylesheet();

        $this->assertMatchesRegularExpression(
            '/section\.act[^{]*\{[^}]*text-align:\s*center/s',
            $stylesheet,
            'act pages must be centered'
        );
        $this->assertMatchesRegularExpression(
            '/section\.act h1\s*\{[^}]*font-size/s',
            $stylesheet,
            'the act number heading must be set larger'
        );
        $this->assertMatchesRegularExpression(
            '/section\.act \.act-name\s*\{[^}]*font-size/s',
            $stylesheet,
            'the act name must be set larger'
        );
    }

    /**
     * Invoke one of EpubExporter's private validation methods directly. These ARE the
     * safety net, so the task wants them tested in isolation with deliberately bad fixtures
     * rather than only through the happy-path export.
     */
    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $reflected = new ReflectionMethod(EpubExporter::class, $method);
        $reflected->setAccessible(true);

        return $reflected->invoke($this->exporter(), ...$args);
    }

    public function test_well_formedness_check_throws_on_malformed_xhtml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('act-1.xhtml is not well-formed');

        // An unclosed <p> is valid-ish HTML but not well-formed XML — the exact class of
        // generator bug this gate exists to catch.
        $this->invokePrivate(
            'assertXmlWellFormed',
            '<html xmlns="http://www.w3.org/1999/xhtml"><head><title>x</title></head><body><p>oops</body></html>',
            'act-1.xhtml'
        );
    }

    public function test_well_formedness_check_passes_a_valid_document(): void
    {
        $this->invokePrivate(
            'assertXmlWellFormed',
            '<html xmlns="http://www.w3.org/1999/xhtml"><head><title>x</title></head><body><p>fine</p></body></html>',
            'act-1.xhtml'
        );

        // No exception thrown means the gate let a well-formed document through.
        $this->assertTrue(true);
    }

    public function test_schema_check_throws_on_a_non_conformant_opf(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed EPUB 3 schema validation');

        // A package document with a manifest/spine but no <metadata> block — required by the
        // EPUB 3 OPF grammar, so the vendored RelaxNG schema must reject it.
        $this->invokePrivate(
            'assertOpfMatchesSchema',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="BookId"><manifest/><spine/></package>'
        );
    }

    public function test_a_normally_generated_epub_passes_both_structural_checks(): void
    {
        // Belt-and-braces: re-run BOTH gates against the shipped file from OUTSIDE the
        // service, proving the happy-path export is genuinely conformant (not merely that
        // export() happened not to throw).
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);
                if (! str_ends_with($entry, '.xhtml')) {
                    continue;
                }

                libxml_use_internal_errors(true);
                libxml_clear_errors();
                $document = new DOMDocument;
                $this->assertTrue($document->loadXML((string) $zip->getFromName($entry)), "{$entry} must be well-formed");
                $this->assertSame([], libxml_get_errors(), "{$entry} must parse without libxml errors");
            }

            $opf = new DOMDocument;
            $opf->loadXML((string) $zip->getFromName('OEBPS/book.opf'));
            $this->assertTrue(
                $opf->relaxNGValidate(resource_path('epub-schemas/package-30.rng')),
                'the OPF must validate against the vendored EPUB 3 RelaxNG schema'
            );
        } finally {
            $zip->close();
            @unlink($path);
        }
    }

    public function test_export_throws_epub_export_exception_when_the_tree_is_empty(): void
    {
        // A project whose only act's only chapter has zero scenes: both skip-empty filters
        // fire and nothing survives.
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        Chapter::factory()->for($act)->create();

        $this->expectException(EpubExportException::class);

        $this->exporter()->export($project);
    }

    public function test_export_throws_epub_export_exception_for_a_project_with_no_acts(): void
    {
        $project = Project::factory()->create();

        $this->expectException(EpubExportException::class);

        $this->exporter()->export($project);
    }

    public function test_dc_source_is_normalized_to_the_app_url_not_the_cli_artifact(): void
    {
        config(['app.url' => 'https://imagoldfish.test']);

        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($project);
        $opf = $this->opfOf($path);

        // The library derives dc:source from the request environment; under CLI that is the
        // malformed "http://:/". Task 05 normalizes it to a deterministic app-config value.
        $this->assertStringContainsString('<dc:source>https://imagoldfish.test</dc:source>', $opf);
        $this->assertStringNotContainsString('http://:/', $opf);

        @unlink($path);
    }

    public function test_rendered_documents_carry_the_projects_language(): void
    {
        $project = Project::factory()->create();
        $project->update(['language' => 'fr']);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $tree = $this->exporter()->filteredTree($project);
        $actHtml = $this->exporter()->renderAct($tree->first(), $project);
        $chapterHtml = $this->exporter()->renderChapter($tree->first()->chapters->first(), $project);

        foreach ([$actHtml, $chapterHtml] as $html) {
            $this->assertStringContainsString('lang="fr"', $html);
            $this->assertStringContainsString('xml:lang="fr"', $html);
        }
    }

    // --- Task 09: content options (title formats, descriptions, dividers) ---

    public function test_each_chapter_title_format_drives_both_the_heading_and_the_nav_label(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create(['name' => 'Rising Action']);
        $act->update(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The Storm']);
        $chapter->update(['position' => 12]);
        Scene::factory()->for($chapter)->create();

        $tree = $this->exporter()->filteredTree($project);
        $renderedChapter = $tree->first()->chapters->first();

        // The enum is the single source of truth: the chapter page heading and the
        // TOC/nav label must always match, format by format.
        $expected = [
            'chapter_number_title' => 'Chapter 12: The Storm',
            'number_title' => '12: The Storm',
            'chapter_number' => 'Chapter 12',
            'number' => '12',
            'title' => 'The Storm',
        ];

        foreach ($expected as $format => $label) {
            $settings = PublicationSetting::factory()->for($project)->make(['chapter_title_format' => $format]);

            $chapterHtml = $this->exporter()->renderChapter($renderedChapter, $project, $settings);
            $this->assertStringContainsString("<h1>{$label}</h1>", $chapterHtml, "heading for {$format}");

            $tocHtml = $this->exporter()->renderToc($project, $tree, $settings);
            $this->assertStringContainsString(">{$label}</a>", $tocHtml, "nav label for {$format}");
        }
    }

    public function test_a_nameless_chapter_has_no_dangling_separator_and_no_blank_heading(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create(['name' => '']);
        $chapter->update(['position' => 5]);
        Scene::factory()->for($chapter)->create();

        $tree = $this->exporter()->filteredTree($project);
        $renderedChapter = $tree->first()->chapters->first();

        // Default format on a nameless chapter: "Chapter 5" with no trailing ": ".
        $html = $this->exporter()->renderChapter($renderedChapter, $project);
        $this->assertStringContainsString('<h1>Chapter 5</h1>', $html);
        $this->assertStringNotContainsString('Chapter 5:', $html);

        // Title-only format on a nameless chapter yields no heading element at all.
        $titleOnly = PublicationSetting::factory()->for($project)->make(['chapter_title_format' => 'title']);
        $titleHtml = $this->exporter()->renderChapter($renderedChapter, $project, $titleOnly);
        $this->assertStringNotContainsString('<h1>', $titleHtml);
    }

    public function test_scene_titles_render_only_when_enabled_and_non_empty(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['name' => 'The Meeting', 'contents' => 'Prose.']);
        Scene::factory()->for($chapter)->create(['name' => '', 'contents' => 'More prose.']);

        $tree = $this->exporter()->filteredTree($project);
        $renderedChapter = $tree->first()->chapters->first();

        // Off (default): no scene-title heading at all.
        $off = $this->exporter()->renderChapter($renderedChapter, $project);
        $this->assertStringNotContainsString('The Meeting', $off);
        $this->assertStringNotContainsString('scene-title', $off);

        // On: the named scene gets an <h2>; the empty-named scene renders no blank heading.
        $on = PublicationSetting::factory()->for($project)->make(['include_scene_titles' => true]);
        $html = $this->exporter()->renderChapter($renderedChapter, $project, $on);
        $this->assertStringContainsString('<h2 class="scene-title">The Meeting</h2>', $html);
        $this->assertSame(1, substr_count($html, 'scene-title'), 'the nameless scene must not add a blank title heading');
    }

    public function test_act_description_renders_only_when_enabled_and_non_empty(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create([
            'name' => 'Act Name',
            'description' => '<p>An act description.</p>',
        ]);

        // Off (default): the description is omitted (matches today's behaviour).
        $off = $this->exporter()->renderAct($act, $project);
        $this->assertStringNotContainsString('An act description.', $off);

        // On: rendered as XHTML under the heading.
        $on = PublicationSetting::factory()->for($project)->make(['include_act_descriptions' => true]);
        $html = $this->exporter()->renderAct($act, $project, $on);
        $this->assertStringContainsString('<div class="act-description"><p>An act description.</p></div>', $html);

        // On but empty: no blank element.
        $act->update(['description' => null]);
        $empty = $this->exporter()->renderAct($act->fresh(), $project, $on);
        $this->assertStringNotContainsString('act-description', $empty);
    }

    public function test_chapter_and_scene_descriptions_render_only_when_enabled_and_non_empty(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create(['description' => '<p>Chapter blurb.</p>']);
        Scene::factory()->for($chapter)->create([
            'description' => '<p>Scene blurb.</p>',
            'contents' => 'Scene prose.',
        ]);

        $tree = $this->exporter()->filteredTree($project);
        $renderedChapter = $tree->first()->chapters->first();

        // Off (default): neither description present.
        $off = $this->exporter()->renderChapter($renderedChapter, $project);
        $this->assertStringNotContainsString('Chapter blurb.', $off);
        $this->assertStringNotContainsString('Scene blurb.', $off);

        // On: both present as XHTML.
        $on = PublicationSetting::factory()->for($project)->make([
            'include_chapter_descriptions' => true,
            'include_scene_descriptions' => true,
        ]);
        $html = $this->exporter()->renderChapter($renderedChapter, $project, $on);
        $this->assertStringContainsString('<div class="chapter-description"><p>Chapter blurb.</p></div>', $html);
        $this->assertStringContainsString('<div class="scene-description"><p>Scene blurb.</p></div>', $html);
    }

    public function test_chapter_and_scene_descriptions_toggle_on_but_empty_render_no_element(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create(['description' => null]);
        Scene::factory()->for($chapter)->create(['description' => null, 'contents' => 'Prose.']);

        $tree = $this->exporter()->filteredTree($project);
        $renderedChapter = $tree->first()->chapters->first();

        $on = PublicationSetting::factory()->for($project)->make([
            'include_chapter_descriptions' => true,
            'include_scene_descriptions' => true,
        ]);
        $html = $this->exporter()->renderChapter($renderedChapter, $project, $on);

        $this->assertStringNotContainsString('chapter-description', $html);
        $this->assertStringNotContainsString('scene-description', $html);
    }

    public function test_decorative_divider_replaces_the_horizontal_rule_and_stays_well_formed(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'First.']);
        Scene::factory()->for($chapter)->create(['contents' => 'Second.']);

        $tree = $this->exporter()->filteredTree($project);
        $renderedChapter = $tree->first()->chapters->first();

        $settings = PublicationSetting::factory()->for($project)->make(['divider_type' => 'decorative']);
        $html = $this->exporter()->renderChapter($renderedChapter, $project, $settings);

        $this->assertStringNotContainsString('<hr/>', $html);
        $this->assertStringContainsString('<p class="divider">* * *</p>', $html);

        // The ornament keeps the document XML-well-formed (the export validation gate).
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($html), 'the decorative-divider chapter page must be well-formed');

        // The stylesheet actually declares the ornament rule the class relies on.
        $this->assertMatchesRegularExpression(
            '/p\.divider\s*\{[^}]*text-align:\s*center/s',
            $this->exporter()->stylesheet(),
            'the decorative divider must be centered by the stylesheet'
        );
    }

    public function test_a_description_with_non_xhtml_markup_still_exports_a_valid_package(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        // Persist deliberately non-XHTML markup straight to the column, BYPASSING the
        // sanitizing set-mutator, so the exporter sees an unclosed <p> and a bare void
        // <br> — exactly the shape RichText::toXhtmlFragment() must repair so the shipped
        // .xhtml stays well-formed and clears validatePackage().
        DB::table('chapters')->where('id', $chapter->id)->update([
            'description' => '<p>Unclosed paragraph<br>with a bare void break and an <em>italic run',
        ]);

        PublicationSetting::factory()->for($project)->create(['include_chapter_descriptions' => true]);

        // export() runs validatePackage() internally; a non-well-formed chapter page would
        // throw. A returned path proves the fragment normalised cleanly.
        $path = $this->exporter()->export($project->fresh());

        $chapterEntry = null;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_contains($name, 'chapter-') && str_ends_with($name, '.xhtml')) {
                $chapterEntry = (string) $zip->getFromName($name);
                break;
            }
        }
        $zip->close();

        $this->assertNotNull($chapterEntry, 'the chapter page must be packaged');
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($chapterEntry), 'the chapter page must be well-formed after normalisation');
        $this->assertStringContainsString('Unclosed paragraph', $chapterEntry);

        @unlink($path);
    }

    // --- Task 10: table-of-contents depth ---

    public function test_acts_depth_lists_only_acts_and_folds_chapter_prose_into_one_page(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create(['name' => 'Rising Action']);
        $act->update(['position' => 1]);

        $chapterOne = Chapter::factory()->for($act)->create(['name' => 'First Chapter']);
        Scene::factory()->for($chapterOne)->create(['contents' => 'CHAPTER_ONE_PROSE']);
        $chapterTwo = Chapter::factory()->for($act)->create(['name' => 'Second Chapter']);
        Scene::factory()->for($chapterTwo)->create(['contents' => 'CHAPTER_TWO_PROSE']);

        PublicationSetting::factory()->for($project)->create(['table_of_contents_depth' => 'acts']);

        $path = $this->exporter()->export($project);

        // In-book TOC page: exactly one act link, no chapter links at all.
        $toc = (string) $this->entryOf($path, 'OEBPS/toc.xhtml');
        $tocDom = new DOMDocument;
        $tocDom->loadXML($toc);
        $tocXpath = new DOMXPath($tocDom);
        $this->assertNotNull(
            $tocXpath->query("//*[local-name()='a'][@href='act-{$act->id}.xhtml']")->item(0),
            'the toc page must link to the act'
        );
        $this->assertSame(
            0,
            $tocXpath->query("//*[local-name()='a'][starts-with(@href,'chapter-')]")->length,
            'the toc page must carry no chapter links at Acts depth'
        );

        // EPUB 3 nav document: the act entry, no chapter entries.
        $nav = (string) $this->entryOf($path, 'OEBPS/epub3toc.xhtml');
        $navDom = new DOMDocument;
        libxml_use_internal_errors(true);
        $navDom->loadHTML('<?xml encoding="utf-8"?>'.$nav);
        libxml_clear_errors();
        $navXpath = new DOMXPath($navDom);
        $this->assertNotNull(
            $navXpath->query("//a[@href='act-{$act->id}.xhtml']")->item(0),
            'the nav must list the act'
        );
        $this->assertSame(
            0,
            $navXpath->query("//a[starts-with(@href,'chapter-')]")->length,
            'the nav must carry no chapter entries at Acts depth'
        );

        // The prose is not lost: both chapters are folded into the single act spine page,
        // and no standalone chapter page is packaged.
        $combined = (string) $this->entryOf($path, "OEBPS/act-{$act->id}.xhtml");
        $this->assertStringContainsString('CHAPTER_ONE_PROSE', $combined);
        $this->assertStringContainsString('CHAPTER_TWO_PROSE', $combined);
        $this->assertStringContainsString('First Chapter', $combined);
        $this->assertStringContainsString('Second Chapter', $combined);
        $this->assertFalse(
            $this->epubHasEntryEndingWith($path, "chapter-{$chapterOne->id}.xhtml"),
            'no standalone chapter page is packaged at Acts depth'
        );

        @unlink($path);
    }

    public function test_scenes_depth_adds_scene_anchors_and_a_third_nav_level(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create(['name' => 'Rising Action']);
        $act->update(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The Beginning']);
        $chapter->update(['position' => 1]);

        $namedScene = Scene::factory()->for($chapter)->create(['name' => 'The Meeting', 'contents' => 'Prose one.']);
        $unnamedScene = Scene::factory()->for($chapter)->create(['name' => '', 'contents' => 'Prose two.']);

        PublicationSetting::factory()->for($project)->create(['table_of_contents_depth' => 'scenes']);

        $path = $this->exporter()->export($project);

        // The chapter document carries a real anchor for each scene id — the target the
        // fragment nav/TOC links resolve against.
        $chapterXhtml = (string) $this->entryOf($path, "OEBPS/chapter-{$chapter->id}.xhtml");
        $this->assertStringContainsString("id=\"scene-{$namedScene->id}\"", $chapterXhtml);
        $this->assertStringContainsString("id=\"scene-{$unnamedScene->id}\"", $chapterXhtml);

        // EPUB 3 nav: a third level of per-scene fragment links nested under the chapter,
        // named scene by name, unnamed scene by "Scene {position}".
        $nav = (string) $this->entryOf($path, 'OEBPS/epub3toc.xhtml');
        $navDom = new DOMDocument;
        libxml_use_internal_errors(true);
        $navDom->loadHTML('<?xml encoding="utf-8"?>'.$nav);
        libxml_clear_errors();
        $navXpath = new DOMXPath($navDom);

        $namedLink = $navXpath->query("//a[@href='chapter-{$chapter->id}.xhtml#scene-{$namedScene->id}']")->item(0);
        $this->assertNotNull($namedLink, 'the named scene must be a third-level nav entry');
        $this->assertSame('The Meeting', trim($namedLink->textContent));

        $unnamedLink = $navXpath->query("//a[@href='chapter-{$chapter->id}.xhtml#scene-{$unnamedScene->id}']")->item(0);
        $this->assertNotNull($unnamedLink, 'the unnamed scene must fall back to a positional label');
        $this->assertSame("Scene {$unnamedScene->position}", trim($unnamedLink->textContent));

        // The scene links live nested under the chapter's <li> (the third level), which is
        // itself nested under the act — three levels deep.
        $chapterLi = $navXpath->query("//a[@href='chapter-{$chapter->id}.xhtml']")->item(0)->parentNode;
        $this->assertNotNull(
            $navXpath->query(".//ol//a[contains(@href,'#scene-')]", $chapterLi)->item(0),
            'scene links must be nested inside the chapter entry'
        );

        // The in-book TOC page mirrors the same third level.
        $toc = (string) $this->entryOf($path, 'OEBPS/toc.xhtml');
        $tocDom = new DOMDocument;
        $tocDom->loadXML($toc);
        $tocXpath = new DOMXPath($tocDom);
        $this->assertNotNull(
            $tocXpath->query("//*[local-name()='a'][@href='chapter-{$chapter->id}.xhtml#scene-{$namedScene->id}']")->item(0),
            'the toc page must link to the scene anchor'
        );

        @unlink($path);
    }

    public function test_default_and_chapters_depth_emit_no_scene_anchors(): void
    {
        // The default (Chapters) depth must not change today's chapter page: no scene
        // anchors, matching the overview's defaults===v1 contract.
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        $tree = $this->exporter()->filteredTree($project);
        $renderedChapter = $tree->first()->chapters->first();

        $default = $this->exporter()->renderChapter($renderedChapter, $project);
        $this->assertStringNotContainsString('scene-anchor', $default);
        $this->assertStringNotContainsString('id="scene-', $default);

        $chapters = PublicationSetting::factory()->for($project)->make(['table_of_contents_depth' => 'chapters']);
        $explicit = $this->exporter()->renderChapter($renderedChapter, $project, $chapters);
        $this->assertStringNotContainsString('id="scene-', $explicit);
    }

    /**
     * Resolve the OPF's spine, in reading order, to the manifest hrefs it points at — the
     * same pattern {@see test_front_matter_spine_order_is_title_then_toc_then_story()} uses,
     * extracted here so the new task-11 tests can reuse it.
     *
     * @return array<int, string|null>
     */
    private function spineHrefs(string $path): array
    {
        $dom = new DOMDocument;
        $dom->loadXML($this->opfOf($path));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('opf', 'http://www.idpf.org/2007/opf');

        $hrefById = [];
        foreach ($xpath->query('//opf:manifest/opf:item') as $item) {
            $hrefById[$item->getAttribute('id')] = $item->getAttribute('href');
        }

        $hrefs = [];
        foreach ($xpath->query('//opf:spine/opf:itemref') as $itemref) {
            $hrefs[] = $hrefById[$itemref->getAttribute('idref')] ?? null;
        }

        return $hrefs;
    }

    public function test_enabled_and_non_empty_matter_sections_render_at_the_position_dictated_by_section_order(): void
    {
        $project = Project::factory()->create([
            'dedication' => 'For *everyone* who believed.',
            'acknowledgements' => 'Thanks to my editor.',
            'preface' => 'A word before we begin.',
            'postface' => 'A word after the end.',
        ]);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        // Customise the order: move `postface` before `body` (still after `toc`), matching
        // the task's own example. `title` stays pinned first.
        $order = ['title', 'dedication', 'acknowledgements', 'preface', 'toc', 'postface', 'body', 'appendix'];

        PublicationSetting::factory()->for($project)->create([
            'include_dedication' => true,
            'include_acknowledgements' => true,
            'include_preface' => true,
            'include_postface' => true,
            'section_order' => $order,
        ]);

        $path = $this->exporter()->export($project);

        foreach (['dedication.xhtml', 'acknowledgements.xhtml', 'preface.xhtml', 'postface.xhtml'] as $file) {
            $this->assertTrue(
                $this->epubHasEntryEndingWith($path, $file),
                "expected {$file} to be packaged"
            );
        }

        $hrefs = $this->spineHrefs($path);

        $this->assertSame([
            'title.xhtml',
            'dedication.xhtml',
            'acknowledgements.xhtml',
            'preface.xhtml',
            'toc.xhtml',
            'postface.xhtml',
        ], array_slice($hrefs, 0, 6), 'the front matter must appear in the customised section_order');

        // `postface` was placed before `body`, so the story files must follow it.
        $this->assertStringStartsWith('act-', (string) $hrefs[6], 'the story must follow the reordered postface');

        @unlink($path);
    }

    public function test_disabled_or_empty_matter_sections_are_absent(): void
    {
        $project = Project::factory()->create([
            // Non-empty content, but its toggle stays off.
            'dedication' => 'For everyone.',
            // Toggle on, but the field is empty.
            'preface' => null,
            'acknowledgements' => '',
            'postface' => "   \n",
        ]);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        PublicationSetting::factory()->for($project)->create([
            'include_dedication' => false,
            'include_acknowledgements' => true,
            'include_preface' => true,
            'include_postface' => true,
        ]);

        $path = $this->exporter()->export($project);

        foreach (['dedication.xhtml', 'acknowledgements.xhtml', 'preface.xhtml', 'postface.xhtml'] as $file) {
            $this->assertFalse(
                $this->epubHasEntryEndingWith($path, $file),
                "expected {$file} to be absent (disabled toggle or empty field)"
            );
        }

        @unlink($path);
    }

    public function test_matter_page_applies_smart_typography(): void
    {
        $project = Project::factory()->create([
            'dedication' => 'To "her" -- always.',
        ]);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        PublicationSetting::factory()->for($project)->create(['include_dedication' => true]);

        $path = $this->exporter()->export($project);

        $dedication = (string) $this->entryOf($path, 'OEBPS/dedication.xhtml');
        $this->assertStringNotContainsString('"her"', $dedication, 'straight quotes must be converted');
        $this->assertStringNotContainsString('--', $dedication, 'the double hyphen must become a smart dash');
        $this->assertMatchesRegularExpression('/[\x{201C}\x{201D}]her[\x{201C}\x{201D}]/u', $dedication);
        $this->assertStringContainsString("\u{2013}", $dedication, 'expected a smart en-dash for --');

        @unlink($path);
    }

    public function test_reordering_toc_and_body_changes_the_spine_order(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        // Swap `toc` and `body` relative to the standard order.
        PublicationSetting::factory()->for($project)->create([
            'section_order' => ['title', 'dedication', 'acknowledgements', 'preface', 'body', 'toc', 'postface', 'appendix'],
        ]);

        $path = $this->exporter()->export($project);

        $hrefs = $this->spineHrefs($path);

        $this->assertSame('title.xhtml', $hrefs[0]);
        $this->assertStringStartsWith('act-', (string) $hrefs[1], 'the story must come before the toc when reordered');
        $this->assertStringStartsWith('chapter-', (string) $hrefs[2], 'the chapter page is still part of the story block');
        $this->assertSame('toc.xhtml', $hrefs[3], 'the toc must follow the story when reordered after it');

        @unlink($path);
    }

    public function test_default_section_order_still_produces_a_valid_package_with_no_matter_pages(): void
    {
        // No PublicationSetting row at all (the lazy default): every include_* toggle for
        // front/back matter defaults false, so the export must contain no matter pages even
        // though the Project happens to carry Markdown in those columns — this is the
        // "toggle gates independently of content" half of overview decision #4, exercised
        // through the full export() pipeline via the lazy default.
        $project = Project::factory()->create([
            'dedication' => 'For everyone.',
            'acknowledgements' => 'Thanks.',
            'preface' => 'A preface.',
            'postface' => 'A postface.',
        ]);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        $path = $this->exporter()->export($project);

        foreach (['dedication.xhtml', 'acknowledgements.xhtml', 'preface.xhtml', 'postface.xhtml'] as $file) {
            $this->assertFalse($this->epubHasEntryEndingWith($path, $file));
        }

        @unlink($path);
    }

    /**
     * Task 12: a chapter with a cover, `include_chapter_covers` on. The image bytes and a
     * dedicated cover page must both be packaged, and the cover page must sit in the spine
     * immediately before that chapter's own content page.
     */
    public function test_chapter_cover_page_is_inserted_immediately_before_its_chapter_when_enabled(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('chapter-covers/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        ));

        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create(['cover_image' => 'chapter-covers/cover.png']);
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        PublicationSetting::factory()->for($project)->create(['include_chapter_covers' => true]);

        $path = $this->exporter()->export($project);

        $this->assertTrue(
            $this->epubHasEntryEndingWith($path, "images/chapter-cover-{$chapter->id}-cover.png"),
            'the chapter cover bytes must be packaged'
        );
        $this->assertTrue(
            $this->epubHasEntryEndingWith($path, "chapter-cover-{$chapter->id}.xhtml"),
            'a dedicated chapter cover page must be packaged'
        );

        $hrefs = $this->spineHrefs($path);
        $coverIndex = array_search("chapter-cover-{$chapter->id}.xhtml", $hrefs, true);
        $chapterIndex = array_search("chapter-{$chapter->id}.xhtml", $hrefs, true);

        $this->assertNotFalse($coverIndex, 'the cover page must be in the spine');
        $this->assertNotFalse($chapterIndex, 'the chapter page must be in the spine');
        $this->assertSame($chapterIndex - 1, $coverIndex, 'the cover page must sit immediately before its chapter');

        @unlink($path);
    }

    /**
     * Task 12, overview decision #3: `include_chapter_covers` defaults off, so a chapter
     * with a real cover set must still produce no cover page/image when the toggle is off.
     */
    public function test_chapter_cover_page_is_absent_when_the_toggle_is_off(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('chapter-covers/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        ));

        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create(['cover_image' => 'chapter-covers/cover.png']);
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        // No PublicationSetting row at all — the lazy default's `include_chapter_covers`
        // is false.
        $path = $this->exporter()->export($project);

        $this->assertFalse($this->epubHasEntryEndingWith($path, "images/chapter-cover-{$chapter->id}-cover.png"));
        $this->assertFalse($this->epubHasEntryEndingWith($path, "chapter-cover-{$chapter->id}.xhtml"));

        @unlink($path);
    }

    /**
     * Task 12: a `cover_image` column pointing at a file that no longer exists on the
     * `public` disk must be skipped silently — mirroring how {@see EpubExporter::applyCover()}
     * already treats a missing project cover — never aborting the export.
     */
    public function test_chapter_with_a_missing_cover_file_is_skipped_and_the_export_still_succeeds(): void
    {
        Storage::fake('public');
        // Deliberately never written to the fake disk.

        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create(['cover_image' => 'chapter-covers/missing.png']);
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        PublicationSetting::factory()->for($project)->create(['include_chapter_covers' => true]);

        $path = $this->exporter()->export($project);

        $this->assertFalse($this->epubHasEntryEndingWith($path, "chapter-cover-{$chapter->id}.xhtml"));
        $this->assertTrue($this->epubHasEntryEndingWith($path, "chapter-{$chapter->id}.xhtml"), 'the chapter itself must still export');

        @unlink($path);
    }

    // --- Task 13 (step 1): codex appendix skeleton (text only, no images) ---

    /**
     * Give a project a minimal surviving act/chapter/scene tree so export() has something to
     * package (an empty tree throws before any appendix is reached).
     */
    private function seedMinimalStory(Project $project): void
    {
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);
    }

    public function test_appendix_lists_only_the_selected_types_in_type_then_name_order(): void
    {
        $project = Project::factory()->create();
        $this->seedMinimalStory($project);

        // Two characters (name order must be Aragorn before Zelda) and one location — all three
        // are in the selected types. An organization entry is NOT selected and must be absent.
        $aragorn = CodexEntry::factory()->for($project)->character()->create(['name' => 'Aragorn', 'description' => '<p>A ranger.</p>']);
        $zelda = CodexEntry::factory()->for($project)->character()->create(['name' => 'Zelda', 'description' => '<p>A princess.</p>']);
        $rivendell = CodexEntry::factory()->for($project)->location()->create(['name' => 'Rivendell', 'description' => '<p>An elven refuge.</p>']);
        $fellowship = CodexEntry::factory()->for($project)->organization()->create(['name' => 'The Fellowship', 'description' => '<p>A group.</p>']);

        PublicationSetting::factory()->for($project)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character', 'location'],
        ]);

        $path = $this->exporter()->export($project);

        // The appendix heading page and each selected entry page are packaged.
        $this->assertTrue($this->epubHasEntryEndingWith($path, 'appendix.xhtml'), 'the appendix heading page must be packaged');
        $this->assertTrue($this->epubHasEntryEndingWith($path, "appendix-entry-{$aragorn->id}.xhtml"));
        $this->assertTrue($this->epubHasEntryEndingWith($path, "appendix-entry-{$zelda->id}.xhtml"));
        $this->assertTrue($this->epubHasEntryEndingWith($path, "appendix-entry-{$rivendell->id}.xhtml"));

        // The unselected organization entry is absent.
        $this->assertFalse(
            $this->epubHasEntryEndingWith($path, "appendix-entry-{$fellowship->id}.xhtml"),
            'an entry of an unselected type must not appear in the appendix'
        );

        // Spine order: the appendix heading, then its entries ordered by (type, name):
        // character/Aragorn, character/Zelda, location/Rivendell.
        $hrefs = $this->spineHrefs($path);
        $appendixEntryHrefs = array_values(array_filter(
            $hrefs,
            fn (?string $href) => $href !== null && str_starts_with($href, 'appendix-entry-')
        ));

        $this->assertSame([
            "appendix-entry-{$aragorn->id}.xhtml",
            "appendix-entry-{$zelda->id}.xhtml",
            "appendix-entry-{$rivendell->id}.xhtml",
        ], $appendixEntryHrefs, 'appendix entries must be ordered by (type, name)');

        // The heading page precedes its entries in the spine.
        $headingIndex = array_search('appendix.xhtml', $hrefs, true);
        $firstEntryIndex = array_search("appendix-entry-{$aragorn->id}.xhtml", $hrefs, true);
        $this->assertNotFalse($headingIndex);
        $this->assertNotFalse($firstEntryIndex);
        $this->assertTrue($headingIndex < $firstEntryIndex, 'the appendix heading must precede its entries');

        // The entry page carries the entry name and its (well-formed) description.
        $entryXhtml = (string) $this->entryOf($path, "OEBPS/appendix-entry-{$aragorn->id}.xhtml");
        $this->assertStringContainsString('<h1>Aragorn</h1>', $entryXhtml);
        $this->assertStringContainsString('A ranger.', $entryXhtml);

        // Nav: the entries nest one level under the Appendix nav entry.
        $nav = (string) $this->entryOf($path, 'OEBPS/epub3toc.xhtml');
        $navDom = new DOMDocument;
        libxml_use_internal_errors(true);
        $navDom->loadHTML('<?xml encoding="utf-8"?>'.$nav);
        libxml_clear_errors();
        $navXpath = new DOMXPath($navDom);
        $appendixLink = $navXpath->query("//a[@href='appendix.xhtml']")->item(0);
        $this->assertNotNull($appendixLink, 'the appendix heading must be a nav entry');
        $this->assertNotNull(
            $navXpath->query(".//ol//a[@href='appendix-entry-{$aragorn->id}.xhtml']", $appendixLink->parentNode)->item(0),
            'entry links must be nested under the appendix heading'
        );

        @unlink($path);
    }

    public function test_appendix_is_absent_when_the_toggle_is_off(): void
    {
        $project = Project::factory()->create();
        $this->seedMinimalStory($project);
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Aragorn']);

        // No PublicationSetting row: the lazy default's include_codex_appendix is false.
        $path = $this->exporter()->export($project);

        $this->assertFalse($this->epubHasEntryEndingWith($path, 'appendix.xhtml'));
        $this->assertFalse($this->epubHasEntryEndingWith($path, "appendix-entry-{$entry->id}.xhtml"));

        @unlink($path);
    }

    public function test_appendix_is_absent_when_no_types_are_selected(): void
    {
        $project = Project::factory()->create();
        $this->seedMinimalStory($project);
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Aragorn']);

        // Toggle on, but no entry types chosen — nothing to render.
        PublicationSetting::factory()->for($project)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => [],
        ]);

        $path = $this->exporter()->export($project);

        $this->assertFalse($this->epubHasEntryEndingWith($path, 'appendix.xhtml'));
        $this->assertFalse($this->epubHasEntryEndingWith($path, "appendix-entry-{$entry->id}.xhtml"));

        @unlink($path);
    }

    public function test_appendix_entry_with_non_xhtml_description_still_exports_a_valid_package(): void
    {
        $project = Project::factory()->create();
        $this->seedMinimalStory($project);

        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Broken Entry']);

        // Persist deliberately non-XHTML markup straight to the column, BYPASSING the codex
        // rich-HTML sanitizer, so the exporter sees an unclosed <p> and a bare void <br> — the
        // exact shape RichText::toXhtmlFragment() must repair so the shipped .xhtml stays
        // well-formed and clears validatePackage().
        DB::table('codex_entries')->where('id', $entry->id)->update([
            'description' => '<p>Unclosed paragraph<br>with a bare void break and an <em>italic run',
        ]);

        PublicationSetting::factory()->for($project)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character'],
        ]);

        // export() runs validatePackage() internally; a non-well-formed entry page would throw.
        $path = $this->exporter()->export($project);

        $entryXhtml = (string) $this->entryOf($path, "OEBPS/appendix-entry-{$entry->id}.xhtml");
        $this->assertNotSame('', $entryXhtml, 'the appendix entry page must be packaged');

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($entryXhtml), 'the appendix entry page must be well-formed after normalisation');
        $this->assertStringContainsString('Unclosed paragraph', $entryXhtml);

        @unlink($path);
    }

    // --- Task 13 (step 2): codex appendix images ---

    /**
     * A 1x1 PNG on the fake public disk, standing in for a codex media image file.
     */
    private function fakeImageBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        );
    }

    /**
     * Task 13 step 2: with `appendix_include_images` on, the entry's FIRST media image is
     * embedded on its page — bytes packaged and referenced by the page's <img>. A SECOND image
     * on the same entry is deliberately NOT embedded (V1 first-image-only scope limit).
     */
    public function test_appendix_embeds_only_the_first_media_image_when_include_images_is_on(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('codex-media/first.png', $this->fakeImageBytes());
        Storage::disk('public')->put('codex-media/second.png', $this->fakeImageBytes());

        $project = Project::factory()->create();
        $this->seedMinimalStory($project);

        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Aragorn',
            'description' => '<p>A ranger.</p>',
        ]);
        // Two reference images; the creating() hook gives them positions 1 then 2, so the
        // (collection, position) eager-load order makes first.png the "first image".
        CodexMedia::factory()->for($entry, 'entry')->referenceImage()->create([
            'path' => 'codex-media/first.png',
            'mime_type' => 'image/png',
        ]);
        CodexMedia::factory()->for($entry, 'entry')->referenceImage()->create([
            'path' => 'codex-media/second.png',
            'mime_type' => 'image/png',
        ]);

        PublicationSetting::factory()->for($project)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character'],
            'appendix_include_images' => true,
        ]);

        $path = $this->exporter()->export($project);

        // The first image's bytes are packaged, namespaced by the entry id; the second is not.
        $this->assertTrue(
            $this->epubHasEntryEndingWith($path, "images/appendix-entry-{$entry->id}-first.png"),
            'the first media image must be packaged'
        );
        $this->assertFalse(
            $this->epubHasEntryEndingWith($path, "images/appendix-entry-{$entry->id}-second.png"),
            'only the FIRST image is embedded (V1 scope limit)'
        );

        // The entry page references the packaged image above its description.
        $entryXhtml = (string) $this->entryOf($path, "OEBPS/appendix-entry-{$entry->id}.xhtml");
        $this->assertStringContainsString("images/appendix-entry-{$entry->id}-first.png", $entryXhtml);
        $this->assertStringContainsString('A ranger.', $entryXhtml);

        @unlink($path);
    }

    /**
     * Task 13 step 2: a media row pointing at a file that is no longer on the `public` disk is
     * skipped SILENTLY — the entry page still renders (text only) and the export still succeeds
     * and validates. Mirrors the missing chapter-cover / project-cover behaviour.
     */
    public function test_appendix_entry_with_a_missing_image_file_is_skipped_and_export_still_validates(): void
    {
        Storage::fake('public');
        // The media path below is deliberately never written to the fake disk.

        $project = Project::factory()->create();
        $this->seedMinimalStory($project);

        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Aragorn',
            'description' => '<p>A ranger.</p>',
        ]);
        CodexMedia::factory()->for($entry, 'entry')->referenceImage()->create([
            'path' => 'codex-media/missing.png',
            'mime_type' => 'image/png',
        ]);

        PublicationSetting::factory()->for($project)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character'],
            'appendix_include_images' => true,
        ]);

        // export() runs validatePackage() internally; a missing file must not abort it.
        $path = $this->exporter()->export($project);

        $this->assertFalse(
            $this->epubHasEntryEndingWith($path, "images/appendix-entry-{$entry->id}-missing.png"),
            'a missing image file must not be packaged'
        );

        // The entry page is still there, with the description and no <img>.
        $entryXhtml = (string) $this->entryOf($path, "OEBPS/appendix-entry-{$entry->id}.xhtml");
        $this->assertStringContainsString('A ranger.', $entryXhtml);
        $this->assertStringNotContainsString('<img', $entryXhtml, 'a skipped image must leave no <img> on the page');

        @unlink($path);
    }

    /**
     * Task 13 step 2 / overview decision #3: `appendix_include_images` defaults off, so an entry
     * with a real image on disk must still package NO image bytes when the toggle is off.
     */
    public function test_appendix_packages_no_image_bytes_when_include_images_is_off(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('codex-media/first.png', $this->fakeImageBytes());

        $project = Project::factory()->create();
        $this->seedMinimalStory($project);

        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Aragorn',
            'description' => '<p>A ranger.</p>',
        ]);
        CodexMedia::factory()->for($entry, 'entry')->referenceImage()->create([
            'path' => 'codex-media/first.png',
            'mime_type' => 'image/png',
        ]);

        // Appendix on, but images explicitly off.
        PublicationSetting::factory()->for($project)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character'],
            'appendix_include_images' => false,
        ]);

        $path = $this->exporter()->export($project);

        // The appendix and the entry page are present, but no image bytes are packaged.
        $this->assertTrue($this->epubHasEntryEndingWith($path, "appendix-entry-{$entry->id}.xhtml"));
        $this->assertFalse(
            $this->epubHasEntryEndingWith($path, "images/appendix-entry-{$entry->id}-first.png"),
            'no image bytes may be packaged when appendix_include_images is off'
        );

        $entryXhtml = (string) $this->entryOf($path, "OEBPS/appendix-entry-{$entry->id}.xhtml");
        $this->assertStringNotContainsString('<img', $entryXhtml);

        @unlink($path);
    }
}
