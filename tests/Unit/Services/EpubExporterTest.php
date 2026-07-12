<?php

namespace Tests\Unit\Services;

use App\Exceptions\EpubExportException;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Services\EpubExporter;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        return new EpubExporter;
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
}
