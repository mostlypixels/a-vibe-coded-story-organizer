<?php

namespace Tests\Unit\Services;

use App\Exceptions\EpubExportException;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Project;
use App\Models\PublicationSetting;
use App\Models\Scene;
use App\Services\CoverImageService;
use App\Services\EpubExporter;
use App\Support\RichTextFields;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class EpubExporterTest extends TestCase
{
    use RefreshDatabase;

    private function exporter(): EpubExporter
    {
        return new EpubExporter(app(CoverImageService::class));
    }

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

    public function test_it_keeps_chapters_with_no_scenes(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();

        $withScenes = Chapter::factory()->for($act)->create();
        Scene::factory()->for($withScenes)->create();

        // An empty chapter is a deliberate placeholder, not an accident: dropping it would
        // shift every later chapter's number the moment the author starts writing it.
        $empty = Chapter::factory()->for($act)->create();

        $tree = $this->exporter()->actTree($book);

        $this->assertCount(1, $tree);
        $this->assertCount(2, $tree->first()->chapters);
        $this->assertTrue($tree->first()->chapters->first()->is($withScenes));
        $this->assertTrue($tree->first()->chapters->last()->is($empty));
    }

    public function test_it_keeps_acts_whose_chapters_are_all_empty(): void
    {
        [, $book] = $this->projectWithBook();

        $writtenAct = Act::factory()->for($book)->create();
        $writtenChapter = Chapter::factory()->for($writtenAct)->create();
        Scene::factory()->for($writtenChapter)->create();

        // Act 2's only chapter has zero scenes — the act still exports its divider.
        $outlinedAct = Act::factory()->for($book)->create();
        Chapter::factory()->for($outlinedAct)->create();

        // Act 3 has no chapters at all — still a divider.
        $bareAct = Act::factory()->for($book)->create();

        $tree = $this->exporter()->actTree($book);

        $this->assertSame(
            [$writtenAct->id, $outlinedAct->id, $bareAct->id],
            $tree->pluck('id')->all()
        );
        $this->assertCount(0, $tree->last()->chapters);
    }

    public function test_the_book_tree_is_position_ordered_at_every_level(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        // Create scenes out of position order, then force positions 3, 1, 2.
        $third = Scene::factory()->for($chapter)->create(['contents' => 'gamma']);
        $first = Scene::factory()->for($chapter)->create(['contents' => 'alpha']);
        $second = Scene::factory()->for($chapter)->create(['contents' => 'beta']);
        $third->update(['position' => 3]);
        $first->update(['position' => 1]);
        $second->update(['position' => 2]);

        // A second act with a lower position created afterwards must still sort first.
        $laterButFirst = Act::factory()->for($book)->create();
        $laterButFirst->update(['position' => 0]);
        $chapterOfFirst = Chapter::factory()->for($laterButFirst)->create();
        Scene::factory()->for($chapterOfFirst)->create();

        $tree = $this->exporter()->actTree($book);

        $this->assertTrue($tree->first()->is($laterButFirst), 'Acts must sort by position, not insertion.');

        $scenes = $tree->last()->chapters->first()->scenes;
        $this->assertSame([1, 2, 3], $scenes->pluck('position')->all());
        $this->assertSame(['alpha', 'beta', 'gamma'], $scenes->pluck('contents')->all());
    }

    public function test_act_page_renders_the_derived_number_not_the_raw_position(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create([
            'name' => 'The Gathering Storm',
            'description' => 'SECRET_DESCRIPTION',
        ]);
        // A gap: the only act in the book, but its `position` is not 1. The rendered
        // page must show the book-wide RANK (1), never the raw `position` column
        // (continuous numbering, StoryNumbering).
        $act->update(['position' => 2]);

        $html = $this->exporter()->renderAct($act, $book);

        $this->assertStringContainsString('Act 1', $html);
        $this->assertStringNotContainsString('Act 2', $html);
        $this->assertStringContainsString('The Gathering Storm', $html);
        $this->assertStringNotContainsString('SECRET_DESCRIPTION', $html);
    }

    public function test_act_page_with_blank_name_renders_number_only(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create(['name' => '']);
        $act->update(['position' => 1]);

        $html = $this->exporter()->renderAct($act, $book);

        $this->assertStringContainsString('Act 1', $html);
        // No empty name paragraph when the name is blank.
        $this->assertStringNotContainsString('class="act-name"', $html);
    }

    public function test_chapter_page_renders_hr_joined_scenes_without_titles_or_description(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create([
            'name' => 'A Long Expected Party',
            'description' => 'SECRET_DESCRIPTION',
        ]);
        // A gap: sole chapter in the book, so its derived number is 1 regardless of
        // `position` (StoryNumbering).
        $chapter->update(['position' => 3]);

        Scene::factory()->for($chapter)->create(['name' => 'SCENE_ONE_TITLE', 'contents' => 'First scene prose.']);
        Scene::factory()->for($chapter)->create(['name' => 'SCENE_TWO_TITLE', 'contents' => 'Second scene prose.']);

        $tree = $this->exporter()->actTree($book);
        $renderedChapter = $tree->first()->chapters->first();

        $html = $this->exporter()->renderChapter($renderedChapter, $book);

        $this->assertStringContainsString('Chapter 1: A Long Expected Party', $html);
        $this->assertStringContainsString('First scene prose.', $html);
        $this->assertStringContainsString('Second scene prose.', $html);
        $this->assertStringContainsString('<hr/>', $html);
        $this->assertStringNotContainsString('SECRET_DESCRIPTION', $html);
        $this->assertStringNotContainsString('SCENE_ONE_TITLE', $html);
        $this->assertStringNotContainsString('SCENE_TWO_TITLE', $html);
    }

    // --- Continuous numbering: act/chapter numbers are book-wide ranks ---

    public function test_chapter_numbers_run_continuous_across_an_act_boundary(): void
    {
        [, $book] = $this->projectWithBook();

        $actOne = Act::factory()->for($book)->create();
        $actOneChapterOne = Chapter::factory()->for($actOne)->create();
        $actOneChapterTwo = Chapter::factory()->for($actOne)->create();

        $actTwo = Act::factory()->for($book)->create();
        $actTwoChapterOne = Chapter::factory()->for($actTwo)->create();

        $tree = $this->exporter()->actTree($book);
        $numbering = $tree->pluck('chapters')->flatten();

        // The count does not reset at the act boundary: the first chapter of Act 2 picks
        // up where Act 1's chapters left off.
        $this->assertStringContainsString(
            'Chapter 1',
            $this->exporter()->renderChapter($numbering->firstWhere('id', $actOneChapterOne->id), $book)
        );
        $this->assertStringContainsString(
            'Chapter 2',
            $this->exporter()->renderChapter($numbering->firstWhere('id', $actOneChapterTwo->id), $book)
        );
        $this->assertStringContainsString(
            'Chapter 3',
            $this->exporter()->renderChapter($numbering->firstWhere('id', $actTwoChapterOne->id), $book)
        );
    }

    public function test_act_numbers_are_continuous_and_gap_free_after_an_act_is_deleted(): void
    {
        [, $book] = $this->projectWithBook();

        $first = Act::factory()->for($book)->create(['name' => 'First']);
        $middle = Act::factory()->for($book)->create(['name' => 'Middle']);
        $last = Act::factory()->for($book)->create(['name' => 'Last']);

        $middle->delete();

        $survivors = $this->exporter()->actTree($book);
        $this->assertSame([$first->id, $last->id], $survivors->pluck('id')->all());

        // The survivors number 1, 2 — gap-free — never the act with the deleted act's
        // position left in between (which would read 1, 3).
        $this->assertStringContainsString('Act 1', $this->exporter()->renderAct($survivors->first(), $book));
        $this->assertStringContainsString('Act 2', $this->exporter()->renderAct($survivors->last(), $book));
        $this->assertStringNotContainsString('Act 3', $this->exporter()->renderAct($survivors->last(), $book));
    }

    public function test_chapter_numbers_stay_gap_free_after_a_chapter_is_deleted_leaving_placeholders(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();

        $first = Chapter::factory()->for($act)->create(['name' => 'Written']);
        Scene::factory()->for($first)->create();

        $toDelete = Chapter::factory()->for($act)->create(['name' => 'Deleted']);

        // A placeholder with no scenes at all — still exported and still counted.
        $placeholder = Chapter::factory()->for($act)->create(['name' => 'Placeholder']);

        $toDelete->delete();

        $tree = $this->exporter()->actTree($book);
        $survivingChapters = $tree->first()->chapters;
        $this->assertSame([$first->id, $placeholder->id], $survivingChapters->pluck('id')->all());

        $this->assertStringContainsString(
            'Chapter 1: Written',
            $this->exporter()->renderChapter($survivingChapters->first(), $book)
        );
        // Gap-free: the placeholder reads 2, not 3 (the deleted chapter's old position).
        $this->assertStringContainsString(
            'Chapter 2: Placeholder',
            $this->exporter()->renderChapter($survivingChapters->last(), $book)
        );
    }

    public function test_the_toc_and_nav_agree_with_the_headings_across_an_act_boundary(): void
    {
        [, $book] = $this->projectWithBook();

        $actOne = Act::factory()->for($book)->create(['name' => 'Act One']);
        $chapterOne = Chapter::factory()->for($actOne)->create(['name' => 'First']);
        Scene::factory()->for($chapterOne)->create();
        $chapterTwo = Chapter::factory()->for($actOne)->create(['name' => 'Second']);
        Scene::factory()->for($chapterTwo)->create();

        $actTwo = Act::factory()->for($book)->create(['name' => 'Act Two']);
        $chapterThree = Chapter::factory()->for($actTwo)->create(['name' => 'Third']);
        Scene::factory()->for($chapterThree)->create();

        $path = $this->exporter()->export($book);

        $nav = (string) $this->entryOf($path, 'OEBPS/epub3toc.xhtml');
        $toc = (string) $this->entryOf($path, 'OEBPS/toc.xhtml');

        foreach (['Chapter 1: First', 'Chapter 2: Second', 'Chapter 3: Third'] as $label) {
            $this->assertStringContainsString($label, $nav, "nav label missing: {$label}");
            $this->assertStringContainsString($label, $toc, "toc label missing: {$label}");
        }

        $chapterThreeXhtml = (string) $this->entryOf($path, "OEBPS/chapter-{$chapterThree->id}.xhtml");
        $this->assertStringContainsString('Chapter 3: Third', $chapterThreeXhtml);

        @unlink($path);
    }

    public function test_canonical_punctuation_in_scene_contents_carries_through_the_epub_unchanged(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create([
            'contents' => "A dash \u{2013} and a range \u{2014} and an ellipsis\u{2026} and \u{201C}quotes\u{201D}.",
        ]);

        $tree = $this->exporter()->actTree($book);
        $html = $this->exporter()->renderChapter($tree->first()->chapters->first(), $book);

        // Import normalizes punctuation before it ever reaches Scene.contents,
        // so the exporter carries it through instead of converting it itself.
        $this->assertStringContainsString("\u{2013}", $html, 'en-dash must survive export');
        $this->assertStringContainsString("\u{2014}", $html, 'em-dash must survive export');
        $this->assertStringContainsString("\u{2026}", $html, 'ellipsis must survive export');
        $this->assertStringContainsString("\u{201C}", $html, 'opening curly quote must survive export');
        $this->assertStringContainsString("\u{201D}", $html, 'closing curly quote must survive export');

        // The shared accessor renders the same stored punctuation.
        $shared = $scene->fresh()->renderedContents;
        $this->assertStringContainsString("\u{2013}", $shared);
        $this->assertStringContainsString("\u{2026}", $shared);
        $this->assertStringContainsString("\u{201C}quotes\u{201D}", $shared);
    }

    public function test_a_scene_with_canonical_characters_exports_intact_and_well_formed(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create([
            'contents' => "\u{201C}Wait\u{2014}\u{201D}she said\u{2026} \u{2018}n\u{2019} then stopped.",
        ]);

        $path = $this->exporter()->export($book);

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
        $this->assertStringContainsString("\u{201C}Wait\u{2014}\u{201D}she said\u{2026}", $chapterEntry);
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($chapterEntry), 'canonical punctuation must not break well-formedness');

        @unlink($path);
    }

    public function test_strikethrough_and_task_list_render_as_real_markup_in_the_epub(): void
    {
        // The isolated EPUB converter carries the Strikethrough and TaskList
        // extensions, so this markup renders as real HTML instead of literal
        // tildes/brackets — matching what the editor and Scene::renderedContents()
        // already produce via GFM.
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create([
            'contents' => "This is ~~struck~~ text.\n\n- [ ] todo\n- [x] done",
        ]);

        $tree = $this->exporter()->actTree($book);
        $html = $this->exporter()->renderChapter($tree->first()->chapters->first(), $book);

        $this->assertStringContainsString('<s>struck</s>', $html, 'strikethrough must render as <s>, not literal tildes');
        $this->assertStringNotContainsString('~~', $html);
        // `<del>` belongs to generated revision diffs and is not an author tag.
        $this->assertStringNotContainsString('<del>', $html);

        $this->assertStringContainsString('type="checkbox"', $html, 'task list items must render as real checkboxes');
        $this->assertStringContainsString('checked', $html, 'the checked item must render its checked state');
        $this->assertStringNotContainsString('[ ] todo', $html, 'unchecked item must not render as literal brackets');
        $this->assertStringNotContainsString('[x] done', $html, 'checked item must not render as literal brackets');
    }

    public function test_full_metadata_epub_opf_contains_every_field_and_both_identifiers(): void
    {
        Storage::fake('public');
        // A tiny but valid PNG so the cover embed reads real bytes off the public disk.
        Storage::disk('public')->put('book-covers/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        ));

        $project = Project::factory()->create();
        $book = $project->books()->first();
        $book->update([
            'name' => 'The Whole Manuscript',
            'language' => 'fr',
            'author' => 'Jane Author',
            'publisher' => 'Imago Press',
            'rights' => 'Copyright 2026 Jane Author',
            'isbn' => '978-0-306-40615-7',
            'cover_image' => 'book-covers/cover.png',
        ]);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);

        $opf = $this->opfOf($path);

        $this->assertStringContainsString('<dc:title>The Whole Manuscript</dc:title>', $opf);
        $this->assertStringContainsString('<dc:language>fr</dc:language>', $opf);
        $this->assertStringContainsString('Jane Author', $opf, 'dc:creator expected');
        $this->assertStringContainsString('<dc:creator', $opf);
        $this->assertStringContainsString('<dc:publisher>Imago Press</dc:publisher>', $opf);
        $this->assertStringContainsString('<dc:rights>Copyright 2026 Jane Author</dc:rights>', $opf);

        // Both identifiers: the always-present generated URN AND the ISBN as a second one.
        $this->assertStringContainsString("urn:imagoldfish:book:{$book->id}", $opf);
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
        // A plain project: language defaults to 'en', every optional book field is null.
        $project = Project::factory()->create(['name' => 'Bare Bones']);
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);

        $opf = $this->opfOf($path);

        $this->assertStringContainsString('<dc:title>Bare Bones</dc:title>', $opf);
        $this->assertStringContainsString('<dc:language>en</dc:language>', $opf);
        $this->assertStringContainsString("urn:imagoldfish:book:{$book->id}", $opf);

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

    /** Keep lazy and explicit publication defaults content-identical. */
    public function test_defaults_v1_regression_lazy_default_and_explicit_default_row_produce_byte_identical_epubs(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('book-covers/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        ));

        $project = Project::factory()->create();
        $book = $project->books()->first();
        $book->update([
            'name' => 'The Whole Manuscript',
            'author' => 'Jane Author',
            'publisher' => 'Imago Press',
            'rights' => 'Copyright 2026 Jane Author',
            'isbn' => '978-0-306-40615-7',
            'cover_image' => 'book-covers/cover.png',
        ]);
        $act = Act::factory()->for($book)->create(['name' => 'Act One']);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'A Beginning']);
        Scene::factory()->for($chapter)->create();
        Scene::factory()->for($chapter)->create();

        $lazyPath = $this->exporter()->export($book->fresh());

        // Every column set to the literal default value — PublicationSettingFactory's
        // definition() mirrors Book::publicationSettingOrDefault() field-for-field.
        PublicationSetting::factory()->for($book)->create();

        $explicitPath = $this->exporter()->export($book->fresh());

        // Compare the two packages entry-by-entry rather than as one raw byte stream. The
        // guard's intent is that the lazy-default and explicit-default paths produce
        // identical CONTENT; the only value that legitimately differs between two separate
        // export() calls is the OPF publication timestamp (dc:date / dcterms:modified),
        // which the epub library stamps from time() and which drifts by a second when the
        // two back-to-back exports straddle a wall-clock boundary (common under the
        // parallel test runner). Normalising just those two timestamp lines keeps the
        // byte-for-byte content comparison exact while immunising the guard against that
        // pre-existing race.
        $this->assertContentIdenticalIgnoringOpfTimestamp(
            $lazyPath,
            $explicitPath,
            'a book with no PublicationSetting row and one with an explicit all-defaults row must export identical epub content'
        );

        // Content-level sanity check on top of the byte match: today's chapter-heading
        // format, <hr/>-joined scenes with no titles, and metadata present because the
        // columns are set — the exact shape a default book must keep.
        $opf = $this->opfOf($explicitPath);
        $this->assertStringContainsString('<dc:creator', $opf);
        $this->assertStringContainsString('<dc:publisher>Imago Press</dc:publisher>', $opf);
        $this->assertStringContainsString('<dc:rights>Copyright 2026 Jane Author</dc:rights>', $opf);
        $this->assertStringContainsString('urn:isbn:978-0-306-40615-7', $opf);
        $this->assertStringContainsString('<meta name="cover" content="CoverImage"', $opf);

        $chapterXhtml = $this->exporter()->renderChapter($chapter->fresh()->load('scenes'), $book);
        $this->assertStringContainsString('Chapter 1: A Beginning', $chapterXhtml);
        $this->assertStringContainsString('<hr', $chapterXhtml);

        @unlink($lazyPath);
        @unlink($explicitPath);
    }

    public function test_include_author_false_omits_dc_creator_but_keeps_other_metadata(): void
    {
        [, $book] = $this->projectWithBook();
        $book->update(['author' => 'Jane Author', 'publisher' => 'Imago Press']);
        PublicationSetting::factory()->for($book)->create(['include_author' => false]);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);
        $opf = $this->opfOf($path);

        $this->assertStringNotContainsString('<dc:creator', $opf);
        $this->assertStringContainsString('<dc:publisher>Imago Press</dc:publisher>', $opf, 'publisher stays gated independently');

        @unlink($path);
    }

    public function test_include_publisher_false_omits_dc_publisher(): void
    {
        [, $book] = $this->projectWithBook();
        $book->update(['publisher' => 'Imago Press']);
        PublicationSetting::factory()->for($book)->create(['include_publisher' => false]);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);
        $opf = $this->opfOf($path);

        $this->assertStringNotContainsString('<dc:publisher', $opf);

        @unlink($path);
    }

    public function test_include_rights_false_omits_dc_rights(): void
    {
        [, $book] = $this->projectWithBook();
        $book->update(['rights' => 'Copyright 2026 Jane Author']);
        PublicationSetting::factory()->for($book)->create(['include_rights' => false]);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);
        $opf = $this->opfOf($path);

        $this->assertStringNotContainsString('<dc:rights', $opf);

        @unlink($path);
    }

    public function test_include_isbn_false_omits_urn_isbn_identifier_but_keeps_generated_urn(): void
    {
        [, $book] = $this->projectWithBook();
        $book->update(['isbn' => '978-0-306-40615-7']);
        PublicationSetting::factory()->for($book)->create(['include_isbn' => false]);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);
        $opf = $this->opfOf($path);

        $this->assertStringNotContainsString('urn:isbn:', $opf);
        $this->assertStringContainsString("urn:imagoldfish:book:{$book->id}", $opf, 'the generated URN identifier is unconditional');
        $this->assertSame(1, substr_count($opf, '<dc:identifier'));

        @unlink($path);
    }

    public function test_include_book_cover_false_omits_cover_but_keeps_title_urn_and_accessibility(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('book-covers/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        ));

        $project = Project::factory()->create(['name' => 'The Whole Manuscript']);
        $book = $project->books()->first();
        $book->update(['cover_image' => 'book-covers/cover.png']);
        PublicationSetting::factory()->for($book)->create(['include_book_cover' => false]);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);
        $opf = $this->opfOf($path);

        $this->assertStringNotContainsString('<meta name="cover"', $opf);
        $this->assertFalse($this->epubHasEntryEndingWith($path, 'cover.png'), 'cover bytes must not be packaged');

        // Unconditional metadata is unaffected by the toggle.
        $this->assertStringContainsString('<dc:title>The Whole Manuscript</dc:title>', $opf);
        $this->assertStringContainsString("urn:imagoldfish:book:{$book->id}", $opf);
        $this->assertStringContainsString('schema:accessibilitySummary', $opf);

        @unlink($path);
    }

    public function test_toc_nav_is_two_level_with_chapters_nested_under_acts(): void
    {
        [, $book] = $this->projectWithBook();

        $act = Act::factory()->for($book)->create(['name' => 'Rising Action']);
        $act->update(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The Beginning']);
        $chapter->update(['position' => 1]);
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);

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
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);

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
        [, $book] = $this->projectWithBook();

        $act = Act::factory()->for($book)->create(['name' => 'Rising Action']);
        $act->update(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The Beginning']);
        $chapter->update(['position' => 1]);
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);

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

    public function test_title_page_renders_the_books_display_name_as_a_centered_larger_heading(): void
    {
        $project = Project::factory()->create(['name' => 'A Very Large Story']);
        $book = $project->books()->first();

        $html = $this->exporter()->renderTitlePage($book);

        $dom = new DOMDocument;
        $dom->loadXML($html);
        // See the local-name() note in test_toc_page_links_to_the_correct_act_and_chapter_files.
        $xpath = new DOMXPath($dom);

        $heading = $xpath->query("//*[local-name()='h1'][@class='story-title']")->item(0);
        $this->assertNotNull($heading, 'the title page must have an h1.story-title heading');
        // The book has no name of its own, so its displayName() falls back to the project's.
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
     * safety net, so they get their own fixtures — bad ones and good ones — rather
     * than only the happy-path export.
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
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);

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

    public function test_export_throws_epub_export_exception_when_the_book_has_no_scenes_anywhere(): void
    {
        // An outline with acts and chapters but nothing written: the export would be a book
        // of blank pages, so the guard still refuses — it just triggers on "no scenes"
        // rather than on an empty (filtered) tree.
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        Chapter::factory()->for($act)->create();
        Chapter::factory()->for($act)->create();

        $this->expectException(EpubExportException::class);

        $this->exporter()->export($book);
    }

    public function test_export_throws_epub_export_exception_for_a_book_with_no_acts(): void
    {
        $project = Project::factory()->create();
        $book = $project->books()->first();

        $this->expectException(EpubExportException::class);

        $this->exporter()->export($book);
    }

    public function test_dc_source_is_normalized_to_the_app_url_not_the_cli_artifact(): void
    {
        config(['app.url' => 'https://imagoldfish.test']);

        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $path = $this->exporter()->export($book);
        $opf = $this->opfOf($path);

        // The library derives dc:source from the request environment; under CLI that is the
        // malformed "http://:/". The exporter normalizes it to a deterministic app-config value.
        $this->assertStringContainsString('<dc:source>https://imagoldfish.test</dc:source>', $opf);
        $this->assertStringNotContainsString('http://:/', $opf);

        @unlink($path);
    }

    public function test_rendered_documents_carry_the_books_language(): void
    {
        [, $book] = $this->projectWithBook();
        $book->update(['language' => 'fr']);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $tree = $this->exporter()->actTree($book);
        $actHtml = $this->exporter()->renderAct($tree->first(), $book);
        $chapterHtml = $this->exporter()->renderChapter($tree->first()->chapters->first(), $book);

        foreach ([$actHtml, $chapterHtml] as $html) {
            $this->assertStringContainsString('lang="fr"', $html);
            $this->assertStringContainsString('xml:lang="fr"', $html);
        }
    }

    // --- Content options (title formats, descriptions, dividers) ---

    public function test_each_chapter_title_format_drives_both_the_heading_and_the_nav_label(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create(['name' => 'Rising Action']);
        $act->update(['position' => 1]);
        // The chapter's `position` (12) is deliberately not its display number: it is the
        // only chapter in the book, so its derived book-wide number is 1
        // (StoryNumbering) — proving the heading/label follow the rank, not the
        // raw `position` column.
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The Storm']);
        $chapter->update(['position' => 12]);
        Scene::factory()->for($chapter)->create();

        $tree = $this->exporter()->actTree($book);
        $renderedChapter = $tree->first()->chapters->first();

        // The enum is the single source of truth: the chapter page heading and the
        // TOC/nav label must always match, format by format.
        $expected = [
            'chapter_number_title' => 'Chapter 1: The Storm',
            'number_title' => '1: The Storm',
            'chapter_number' => 'Chapter 1',
            'number' => '1',
            'title' => 'The Storm',
        ];

        foreach ($expected as $format => $label) {
            $settings = PublicationSetting::factory()->for($book)->make(['chapter_title_format' => $format]);

            $chapterHtml = $this->exporter()->renderChapter($renderedChapter, $book, $settings);
            $this->assertStringContainsString("<h1>{$label}</h1>", $chapterHtml, "heading for {$format}");

            $tocHtml = $this->exporter()->renderToc($book, $tree, $settings);
            $this->assertStringContainsString(">{$label}</a>", $tocHtml, "nav label for {$format}");
        }
    }

    public function test_a_nameless_chapter_has_no_dangling_separator_and_no_blank_heading(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        // A gap: sole chapter in the book, `position` 5 but a derived number of 1
        // (StoryNumbering).
        $chapter = Chapter::factory()->for($act)->create(['name' => '']);
        $chapter->update(['position' => 5]);
        Scene::factory()->for($chapter)->create();

        $tree = $this->exporter()->actTree($book);
        $renderedChapter = $tree->first()->chapters->first();

        // Default format on a nameless chapter: "Chapter 1" with no trailing ": ".
        $html = $this->exporter()->renderChapter($renderedChapter, $book);
        $this->assertStringContainsString('<h1>Chapter 1</h1>', $html);
        $this->assertStringNotContainsString('Chapter 1:', $html);

        // Title-only format on a nameless chapter yields no heading element at all.
        $titleOnly = PublicationSetting::factory()->for($book)->make(['chapter_title_format' => 'title']);
        $titleHtml = $this->exporter()->renderChapter($renderedChapter, $book, $titleOnly);
        $this->assertStringNotContainsString('<h1>', $titleHtml);
    }

    public function test_scene_titles_render_only_when_enabled_and_non_empty(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['name' => 'The Meeting', 'contents' => 'Prose.']);
        Scene::factory()->for($chapter)->create(['name' => '', 'contents' => 'More prose.']);

        $tree = $this->exporter()->actTree($book);
        $renderedChapter = $tree->first()->chapters->first();

        // Off (default): no scene-title heading at all.
        $off = $this->exporter()->renderChapter($renderedChapter, $book);
        $this->assertStringNotContainsString('The Meeting', $off);
        $this->assertStringNotContainsString('scene-title', $off);

        // On: the named scene gets an <h2>; the empty-named scene renders no blank heading.
        $on = PublicationSetting::factory()->for($book)->make(['include_scene_titles' => true]);
        $html = $this->exporter()->renderChapter($renderedChapter, $book, $on);
        $this->assertStringContainsString('<h2 class="scene-title">The Meeting</h2>', $html);
        $this->assertSame(1, substr_count($html, 'scene-title'), 'the nameless scene must not add a blank title heading');
    }

    public function test_act_description_renders_only_when_enabled_and_non_empty(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create([
            'name' => 'Act Name',
            'description' => '<p>An act description.</p>',
        ]);

        // Off (default): the description is omitted (matches today's behaviour).
        $off = $this->exporter()->renderAct($act, $book);
        $this->assertStringNotContainsString('An act description.', $off);

        // On: rendered as XHTML under the heading.
        $on = PublicationSetting::factory()->for($book)->make(['include_act_descriptions' => true]);
        $html = $this->exporter()->renderAct($act, $book, $on);
        $this->assertStringContainsString('<div class="act-description"><p>An act description.</p></div>', $html);

        // On but empty: no blank element.
        $act->update(['description' => null]);
        $empty = $this->exporter()->renderAct($act->fresh(), $book, $on);
        $this->assertStringNotContainsString('act-description', $empty);
    }

    public function test_chapter_and_scene_descriptions_render_only_when_enabled_and_non_empty(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create(['description' => '<p>Chapter blurb.</p>']);
        Scene::factory()->for($chapter)->create([
            'description' => '<p>Scene blurb.</p>',
            'contents' => 'Scene prose.',
        ]);

        $tree = $this->exporter()->actTree($book);
        $renderedChapter = $tree->first()->chapters->first();

        // Off (default): neither description present.
        $off = $this->exporter()->renderChapter($renderedChapter, $book);
        $this->assertStringNotContainsString('Chapter blurb.', $off);
        $this->assertStringNotContainsString('Scene blurb.', $off);

        // On: both present as XHTML.
        $on = PublicationSetting::factory()->for($book)->make([
            'include_chapter_descriptions' => true,
            'include_scene_descriptions' => true,
        ]);
        $html = $this->exporter()->renderChapter($renderedChapter, $book, $on);
        $this->assertStringContainsString('<div class="chapter-description"><p>Chapter blurb.</p></div>', $html);
        $this->assertStringContainsString('<div class="scene-description"><p>Scene blurb.</p></div>', $html);
    }

    public function test_chapter_and_scene_descriptions_toggle_on_but_empty_render_no_element(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create(['description' => null]);
        Scene::factory()->for($chapter)->create(['description' => null, 'contents' => 'Prose.']);

        $tree = $this->exporter()->actTree($book);
        $renderedChapter = $tree->first()->chapters->first();

        $on = PublicationSetting::factory()->for($book)->make([
            'include_chapter_descriptions' => true,
            'include_scene_descriptions' => true,
        ]);
        $html = $this->exporter()->renderChapter($renderedChapter, $book, $on);

        $this->assertStringNotContainsString('chapter-description', $html);
        $this->assertStringNotContainsString('scene-description', $html);
    }

    public function test_decorative_divider_replaces_the_horizontal_rule_and_stays_well_formed(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'First.']);
        Scene::factory()->for($chapter)->create(['contents' => 'Second.']);

        $tree = $this->exporter()->actTree($book);
        $renderedChapter = $tree->first()->chapters->first();

        $settings = PublicationSetting::factory()->for($book)->make(['divider_type' => 'decorative']);
        $html = $this->exporter()->renderChapter($renderedChapter, $book, $settings);

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
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        // Persist deliberately non-XHTML markup straight to the column, BYPASSING the
        // sanitizing set-mutator, so the exporter sees an unclosed <p> and a bare void
        // <br> — exactly the shape RichText::toXhtmlFragment() must repair so the shipped
        // .xhtml stays well-formed and clears validatePackage().
        DB::table('chapters')->where('id', $chapter->id)->update([
            'description' => '<p>Unclosed paragraph<br>with a bare void break and an <em>italic run',
        ]);

        PublicationSetting::factory()->for($book)->create(['include_chapter_descriptions' => true]);

        // export() runs validatePackage() internally; a non-well-formed chapter page would
        // throw. A returned path proves the fragment normalised cleanly.
        $path = $this->exporter()->export($book);

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

    // --- Table-of-contents depth ---

    public function test_acts_depth_lists_only_acts_and_folds_chapter_prose_into_one_page(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create(['name' => 'Rising Action']);
        $act->update(['position' => 1]);

        $chapterOne = Chapter::factory()->for($act)->create(['name' => 'First Chapter']);
        Scene::factory()->for($chapterOne)->create(['contents' => 'CHAPTER_ONE_PROSE']);
        $chapterTwo = Chapter::factory()->for($act)->create(['name' => 'Second Chapter']);
        Scene::factory()->for($chapterTwo)->create(['contents' => 'CHAPTER_TWO_PROSE']);

        PublicationSetting::factory()->for($book)->create(['table_of_contents_depth' => 'acts']);

        $path = $this->exporter()->export($book);

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
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create(['name' => 'Rising Action']);
        $act->update(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The Beginning']);
        $chapter->update(['position' => 1]);

        $namedScene = Scene::factory()->for($chapter)->create(['name' => 'The Meeting', 'contents' => 'Prose one.']);
        $unnamedScene = Scene::factory()->for($chapter)->create(['name' => '', 'contents' => 'Prose two.']);

        PublicationSetting::factory()->for($book)->create(['table_of_contents_depth' => 'scenes']);

        $path = $this->exporter()->export($book);

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
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        $tree = $this->exporter()->actTree($book);
        $renderedChapter = $tree->first()->chapters->first();

        $default = $this->exporter()->renderChapter($renderedChapter, $book);
        $this->assertStringNotContainsString('scene-anchor', $default);
        $this->assertStringNotContainsString('id="scene-', $default);

        $chapters = PublicationSetting::factory()->for($book)->make(['table_of_contents_depth' => 'chapters']);
        $explicit = $this->exporter()->renderChapter($renderedChapter, $book, $chapters);
        $this->assertStringNotContainsString('id="scene-', $explicit);
    }

    /**
     * Resolve the OPF's spine, in reading order, to the manifest hrefs it points at — the
     * same pattern {@see test_front_matter_spine_order_is_title_then_toc_then_story()} uses,
     * extracted here so the other spine-order tests can reuse it.
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
        $project = Project::factory()->create();
        $book = $project->books()->first();
        $book->update([
            'dedication' => 'For *everyone* who believed.',
            'acknowledgements' => 'Thanks to my editor.',
            'preface' => 'A word before we begin.',
            'postface' => 'A word after the end.',
        ]);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        // Customise the order: move `postface` before `body`, still after `toc`.
        // `title` stays pinned first.
        $order = ['title', 'dedication', 'acknowledgements', 'preface', 'toc', 'postface', 'body', 'appendix'];

        PublicationSetting::factory()->for($book)->create([
            'include_dedication' => true,
            'include_acknowledgements' => true,
            'include_preface' => true,
            'include_postface' => true,
            'section_order' => $order,
        ]);

        $path = $this->exporter()->export($book);

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
        $project = Project::factory()->create();
        $book = $project->books()->first();
        $book->update([
            // Non-empty content, but its toggle stays off.
            'dedication' => 'For everyone.',
            // Toggle on, but the field is empty.
            'preface' => null,
            'acknowledgements' => '',
            'postface' => "   \n",
        ]);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        PublicationSetting::factory()->for($book)->create([
            'include_dedication' => false,
            'include_acknowledgements' => true,
            'include_preface' => true,
            'include_postface' => true,
        ]);

        $path = $this->exporter()->export($book);

        foreach (['dedication.xhtml', 'acknowledgements.xhtml', 'preface.xhtml', 'postface.xhtml'] as $file) {
            $this->assertFalse(
                $this->epubHasEntryEndingWith($path, $file),
                "expected {$file} to be absent (disabled toggle or empty field)"
            );
        }

        @unlink($path);
    }

    public function test_matter_page_carries_through_stored_canonical_punctuation(): void
    {
        $project = Project::factory()->create();
        $book = $project->books()->first();
        $book->update(['dedication' => "To \u{201C}her\u{201D} \u{2013} always."]);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        PublicationSetting::factory()->for($book)->create(['include_dedication' => true]);

        $path = $this->exporter()->export($book);

        // Front matter is imported through the same normalizer as scenes, so
        // it already holds canonical punctuation; the exporter must not alter it.
        $dedication = (string) $this->entryOf($path, 'OEBPS/dedication.xhtml');
        $this->assertMatchesRegularExpression('/[\x{201C}\x{201D}]her[\x{201C}\x{201D}]/u', $dedication);
        $this->assertStringContainsString("\u{2013}", $dedication);

        @unlink($path);
    }

    public function test_reordering_toc_and_body_changes_the_spine_order(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        // Swap `toc` and `body` relative to the standard order.
        PublicationSetting::factory()->for($book)->create([
            'section_order' => ['title', 'dedication', 'acknowledgements', 'preface', 'body', 'toc', 'postface', 'appendix'],
        ]);

        $path = $this->exporter()->export($book);

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
        // though the Book happens to carry Markdown in those columns — this is the
        // "toggle gates independently of content" half of the section rule, exercised
        // through the full export() pipeline via the lazy default.
        $project = Project::factory()->create();
        $book = $project->books()->first();
        $book->update([
            'dedication' => 'For everyone.',
            'acknowledgements' => 'Thanks.',
            'preface' => 'A preface.',
            'postface' => 'A postface.',
        ]);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        $path = $this->exporter()->export($book);

        foreach (['dedication.xhtml', 'acknowledgements.xhtml', 'preface.xhtml', 'postface.xhtml'] as $file) {
            $this->assertFalse($this->epubHasEntryEndingWith($path, $file));
        }

        @unlink($path);
    }

    /**
     * A chapter with a cover, `include_chapter_covers` on. The image bytes and a
     * dedicated cover page must both be packaged, and the cover page must sit in the spine
     * immediately before that chapter's own content page.
     */
    public function test_chapter_cover_page_is_inserted_immediately_before_its_chapter_when_enabled(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('chapter-covers/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        ));

        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create(['cover_image' => 'chapter-covers/cover.png']);
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        PublicationSetting::factory()->for($book)->create(['include_chapter_covers' => true]);

        $path = $this->exporter()->export($book);

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
     * `include_chapter_covers` defaults off, so a chapter
     * with a real cover set must still produce no cover page/image when the toggle is off.
     */
    public function test_chapter_cover_page_is_absent_when_the_toggle_is_off(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('chapter-covers/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        ));

        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create(['cover_image' => 'chapter-covers/cover.png']);
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        // No PublicationSetting row at all — the lazy default's `include_chapter_covers`
        // is false.
        $path = $this->exporter()->export($book);

        $this->assertFalse($this->epubHasEntryEndingWith($path, "images/chapter-cover-{$chapter->id}-cover.png"));
        $this->assertFalse($this->epubHasEntryEndingWith($path, "chapter-cover-{$chapter->id}.xhtml"));

        @unlink($path);
    }

    /**
     * A `cover_image` column pointing at a file that no longer exists on the
     * `public` disk must be skipped silently — mirroring how {@see EpubExporter::applyCover()}
     * already treats a missing book cover — never aborting the export.
     */
    public function test_chapter_with_a_missing_cover_file_is_skipped_and_the_export_still_succeeds(): void
    {
        Storage::fake('public');
        // Deliberately never written to the fake disk.

        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create(['cover_image' => 'chapter-covers/missing.png']);
        Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        PublicationSetting::factory()->for($book)->create(['include_chapter_covers' => true]);

        $path = $this->exporter()->export($book);

        $this->assertFalse($this->epubHasEntryEndingWith($path, "chapter-cover-{$chapter->id}.xhtml"));
        $this->assertTrue($this->epubHasEntryEndingWith($path, "chapter-{$chapter->id}.xhtml"), 'the chapter itself must still export');

        @unlink($path);
    }

    // --- Codex appendix (book-scoped filter) ---

    /**
     * Give a project's book a minimal surviving act/chapter/scene tree so export() has
     * something to package (an empty tree throws before any appendix is reached).
     * Returns the book and its one scene — the appendix now lists only entries THIS
     * scene references (see EpubExporter::addAppendixSection()), so appendix tests
     * attach the entries they want to see via $scene->codexReferences().
     *
     * @return array{0: Book, 1: Scene}
     */
    private function seedMinimalStory(Project $project): array
    {
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'Prose.']);

        return [$book, $scene];
    }

    public function test_appendix_lists_only_the_selected_types_in_type_then_name_order(): void
    {
        $project = Project::factory()->create();
        [$book, $scene] = $this->seedMinimalStory($project);

        // Two characters (name order must be Aragorn before Zelda) and one location — all three
        // are in the selected types. An organization entry is NOT selected and must be absent.
        $aragorn = CodexEntry::factory()->for($project)->character()->create(['name' => 'Aragorn', 'description' => '<p>A ranger.</p>']);
        $zelda = CodexEntry::factory()->for($project)->character()->create(['name' => 'Zelda', 'description' => '<p>A princess.</p>']);
        $rivendell = CodexEntry::factory()->for($project)->location()->create(['name' => 'Rivendell', 'description' => '<p>An elven refuge.</p>']);
        $fellowship = CodexEntry::factory()->for($project)->organization()->create(['name' => 'The Fellowship', 'description' => '<p>A group.</p>']);

        // The appendix filter is per book: only entries THIS book's own scenes
        // reference appear, so the scene must reference every candidate entry.
        $scene->codexReferences()->attach([$aragorn->id, $zelda->id, $rivendell->id, $fellowship->id]);

        PublicationSetting::factory()->for($book)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character', 'location'],
        ]);

        $path = $this->exporter()->export($book);

        // The appendix heading page and each selected entry page are packaged.
        $this->assertTrue($this->epubHasEntryEndingWith($path, 'appendix.xhtml'), 'the appendix heading page must be packaged');
        $this->assertTrue($this->epubHasEntryEndingWith($path, "appendix-entry-{$aragorn->id}.xhtml"));
        $this->assertTrue($this->epubHasEntryEndingWith($path, "appendix-entry-{$zelda->id}.xhtml"));
        $this->assertTrue($this->epubHasEntryEndingWith($path, "appendix-entry-{$rivendell->id}.xhtml"));

        // The unselected organization entry is absent, though referenced.
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

    /**
     * An entry no scene in the book references is excluded even though its type is
     * selected and the toggle is on — the appendix lists only what this book's own
     * scenes reference, never the full codex.
     */
    public function test_appendix_omits_an_entry_no_scene_in_the_book_references(): void
    {
        $project = Project::factory()->create();
        [$book] = $this->seedMinimalStory($project);

        $referenced = CodexEntry::factory()->for($project)->character()->create(['name' => 'Aragorn']);
        $unreferenced = CodexEntry::factory()->for($project)->character()->create(['name' => 'Boromir']);

        $referencingScene = Scene::factory()->for(Chapter::factory()->for(Act::factory()->for($book)))->create();
        $referencingScene->codexReferences()->attach($referenced->id);

        PublicationSetting::factory()->for($book)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character'],
        ]);

        $path = $this->exporter()->export($book);

        $this->assertTrue($this->epubHasEntryEndingWith($path, "appendix-entry-{$referenced->id}.xhtml"));
        $this->assertFalse(
            $this->epubHasEntryEndingWith($path, "appendix-entry-{$unreferenced->id}.xhtml"),
            'an entry no scene references must not appear, even though its type is selected'
        );

        @unlink($path);
    }

    public function test_appendix_is_absent_when_the_toggle_is_off(): void
    {
        $project = Project::factory()->create();
        [$book] = $this->seedMinimalStory($project);
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Aragorn']);

        // No PublicationSetting row: the lazy default's include_codex_appendix is false.
        $path = $this->exporter()->export($book);

        $this->assertFalse($this->epubHasEntryEndingWith($path, 'appendix.xhtml'));
        $this->assertFalse($this->epubHasEntryEndingWith($path, "appendix-entry-{$entry->id}.xhtml"));

        @unlink($path);
    }

    public function test_appendix_is_absent_when_no_types_are_selected(): void
    {
        $project = Project::factory()->create();
        [$book] = $this->seedMinimalStory($project);
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Aragorn']);

        // Toggle on, but no entry types chosen — nothing to render.
        PublicationSetting::factory()->for($book)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => [],
        ]);

        $path = $this->exporter()->export($book);

        $this->assertFalse($this->epubHasEntryEndingWith($path, 'appendix.xhtml'));
        $this->assertFalse($this->epubHasEntryEndingWith($path, "appendix-entry-{$entry->id}.xhtml"));

        @unlink($path);
    }

    public function test_appendix_entry_with_non_xhtml_description_still_exports_a_valid_package(): void
    {
        $project = Project::factory()->create();
        [$book, $scene] = $this->seedMinimalStory($project);

        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Broken Entry']);
        $scene->codexReferences()->attach($entry->id);

        // Persist deliberately non-XHTML markup straight to the column, BYPASSING the codex
        // rich-HTML sanitizer, so the exporter sees an unclosed <p> and a bare void <br> — the
        // exact shape RichText::toXhtmlFragment() must repair so the shipped .xhtml stays
        // well-formed and clears validatePackage().
        DB::table('codex_entries')->where('id', $entry->id)->update([
            'description' => '<p>Unclosed paragraph<br>with a bare void break and an <em>italic run',
        ]);

        PublicationSetting::factory()->for($book)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character'],
        ]);

        // export() runs validatePackage() internally; a non-well-formed entry page would throw.
        $path = $this->exporter()->export($book);

        $entryXhtml = (string) $this->entryOf($path, "OEBPS/appendix-entry-{$entry->id}.xhtml");
        $this->assertNotSame('', $entryXhtml, 'the appendix entry page must be packaged');

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($entryXhtml), 'the appendix entry page must be well-formed after normalisation');
        $this->assertStringContainsString('Unclosed paragraph', $entryXhtml);

        @unlink($path);
    }

    // --- Codex appendix images ---

    /**
     * A 1x1 PNG on the fake public disk, standing in for a codex media image file.
     */
    private function fakeImageBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'
        );
    }

    /** Embed only the first available image for each appendix entry. */
    public function test_appendix_embeds_only_the_first_media_image_when_include_images_is_on(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('codex-media/first.png', $this->fakeImageBytes());
        Storage::disk('public')->put('codex-media/second.png', $this->fakeImageBytes());

        $project = Project::factory()->create();
        [$book, $scene] = $this->seedMinimalStory($project);

        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Aragorn',
            'description' => '<p>A ranger.</p>',
        ]);
        $scene->codexReferences()->attach($entry->id);
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

        PublicationSetting::factory()->for($book)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character'],
            'appendix_include_images' => true,
        ]);

        $path = $this->exporter()->export($book);

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
     * A media row pointing at a file that is no longer on the `public` disk is
     * skipped SILENTLY — the entry page still renders (text only) and the export still succeeds
     * and validates. Mirrors the missing chapter-cover / book-cover behaviour.
     */
    public function test_appendix_entry_with_a_missing_image_file_is_skipped_and_export_still_validates(): void
    {
        Storage::fake('public');
        // The media path below is deliberately never written to the fake disk.

        $project = Project::factory()->create();
        [$book, $scene] = $this->seedMinimalStory($project);

        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Aragorn',
            'description' => '<p>A ranger.</p>',
        ]);
        $scene->codexReferences()->attach($entry->id);
        CodexMedia::factory()->for($entry, 'entry')->referenceImage()->create([
            'path' => 'codex-media/missing.png',
            'mime_type' => 'image/png',
        ]);

        PublicationSetting::factory()->for($book)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character'],
            'appendix_include_images' => true,
        ]);

        // export() runs validatePackage() internally; a missing file must not abort it.
        $path = $this->exporter()->export($book);

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
     * `appendix_include_images` defaults off, so an entry
     * with a real image on disk must still package NO image bytes when the toggle is off.
     */
    public function test_appendix_packages_no_image_bytes_when_include_images_is_off(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('codex-media/first.png', $this->fakeImageBytes());

        $project = Project::factory()->create();
        [$book, $scene] = $this->seedMinimalStory($project);

        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Aragorn',
            'description' => '<p>A ranger.</p>',
        ]);
        $scene->codexReferences()->attach($entry->id);
        CodexMedia::factory()->for($entry, 'entry')->referenceImage()->create([
            'path' => 'codex-media/first.png',
            'mime_type' => 'image/png',
        ]);

        // Appendix on, but images explicitly off.
        PublicationSetting::factory()->for($book)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character'],
            'appendix_include_images' => false,
        ]);

        $path = $this->exporter()->export($book);

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

    // --- Decorative classes (alignment, colour) ---

    public function test_stylesheet_defines_a_rule_for_every_decorative_class(): void
    {
        $stylesheet = $this->exporter()->stylesheet();

        foreach (RichTextFields::decorativeClasses() as $class) {
            $this->assertMatchesRegularExpression(
                '/\.'.preg_quote($class, '/').'\s*\{/',
                $stylesheet,
                "the stylesheet must define a rule for .{$class}"
            );
        }
    }

    public function test_codex_appendix_entry_description_keeps_decorative_classes(): void
    {
        $project = Project::factory()->create();
        [, $scene] = $this->seedMinimalStory($project);

        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Aragorn',
            'description' => '<p class="rt-align-center">A <span class="rt-color-red">ranger</span>.</p>',
        ]);
        $scene->codexReferences()->attach($entry->id);

        $book = $scene->chapter->act->book;
        PublicationSetting::factory()->for($book)->create([
            'include_codex_appendix' => true,
            'appendix_entry_types' => ['character'],
        ]);

        $path = $this->exporter()->export($book);

        $entryXhtml = (string) $this->entryOf($path, "OEBPS/appendix-entry-{$entry->id}.xhtml");
        $this->assertStringContainsString('class="rt-align-center"', $entryXhtml);
        $this->assertStringContainsString('class="rt-color-red"', $entryXhtml);

        @unlink($path);
    }

    /**
     * The counterpart to the appendix test above, and the acceptance criterion
     * that matters most: decoration reaches an appendix but never the narrative.
     * Scene text is Markdown rendered through {@see RichTextProfile::Structural},
     * which strips every class, so raw HTML with a decorative class in the scene
     * body loses that class in the shipped chapter page.
     */
    public function test_scene_body_strips_decorative_classes(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create([
            'contents' => '<p class="rt-align-center rt-color-red">Centered and red.</p>',
        ]);

        $tree = $this->exporter()->actTree($book);
        $renderedChapter = $tree->first()->chapters->first();

        $html = $this->exporter()->renderChapter($renderedChapter, $book);

        $this->assertStringContainsString('<p>Centered and red.</p>', $html);
        $this->assertStringNotContainsString('rt-align-center', $html);
        $this->assertStringNotContainsString('rt-color-red', $html);
    }
}
