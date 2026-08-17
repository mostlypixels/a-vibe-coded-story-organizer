<?php

namespace Tests\Unit\Import;

use App\Exceptions\ImportValidationException;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAlias;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Tag;
use App\Models\User;
use App\Services\Import\ArchiveValidator;
use App\Services\StaticSiteExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Unit-level tests for ArchiveValidator: one test per failure mode from the
 * import spec's "Validation failures" list, each against a small fixture zip
 * built inline, plus the first export → import touchpoint — a real
 * StaticSiteExporter archive must validate cleanly.
 *
 * RefreshDatabase is only needed by the real-export happy paths; the fixture
 * tests never touch the database or the HTTP layer.
 */
class ArchiveValidatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A minimal valid 1x1 PNG, used wherever a media file's bytes must
     * genuinely BE an image.
     */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * The fixture chapter directory. Every manuscript path starts at a book:
     * data/books/<book>/acts/<act>/chapters/<chapter>/.
     */
    private const CHAPTER_DIR = 'data/books/1-book/acts/1-act/chapters/1-chapter';

    /**
     * Scratch directory holding this test's fixture zips; removed in tearDown.
     */
    private string $scratchDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratchDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'archive-validator-test-'.uniqid();
        mkdir($this->scratchDirectory, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->scratchDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->scratchDirectory);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Check 1 — a real zip
    // ------------------------------------------------------------------

    public function test_rejects_a_file_that_is_not_a_zip(): void
    {
        $path = $this->scratchDirectory.DIRECTORY_SEPARATOR.'not-a-zip.zip';
        file_put_contents($path, 'this is plain text pretending to be a zip');

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('not a valid zip archive');

        (new ArchiveValidator)->validate($path);
    }

    // ------------------------------------------------------------------
    // Check 2 — zip-slip
    // ------------------------------------------------------------------

    public function test_rejects_a_path_traversal_entry_and_never_writes_outside_a_scoped_path(): void
    {
        // Canary locations a naive extraction of "../zip-slip-canary.txt" could
        // land in. None may exist after validation — the validator must reject
        // on the NAME alone, before any extraction.
        $canaries = [
            dirname($this->scratchDirectory).DIRECTORY_SEPARATOR.'zip-slip-canary.txt',
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'zip-slip-canary.txt',
            getcwd().DIRECTORY_SEPARATOR.'zip-slip-canary.txt',
        ];

        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $zip->addFromString('../zip-slip-canary.txt', 'escaped');
        });

        try {
            (new ArchiveValidator)->validate($path);
            $this->fail('A path-traversal entry must be rejected.');
        } catch (ImportValidationException $exception) {
            $this->assertStringContainsString('unsafe path', $exception->getMessage());
        }

        foreach ($canaries as $canary) {
            $this->assertFileDoesNotExist($canary, 'validation must never extract an entry, let alone outside a scoped path');
        }
    }

    public function test_rejects_an_absolute_entry_path(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $zip->addFromString('/etc/evil.txt', 'escaped');
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('unsafe path');

        (new ArchiveValidator)->validate($path);
    }

    // ------------------------------------------------------------------
    // Check 3 — arborescence allow-list
    // ------------------------------------------------------------------

    public function test_rejects_an_entry_outside_the_allowed_arborescence(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $zip->addFromString('shell.php', '<?php system($_GET["cmd"]);');
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('unexpected file "shell.php"');

        (new ArchiveValidator)->validate($path);
    }

    public function test_allows_but_ignores_the_books_layer_and_readme(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $zip->addFromString('README.md', '# My Novel');
            $zip->addFromString('books/index.html', '<!doctype html>');
            $zip->addFromString('books/01/index.html', '<!doctype html>');
            $zip->addFromString('books/01/01/01.html', '<!doctype html>');
        });

        (new ArchiveValidator)->validate($path);

        $this->addToAssertionCount(1); // no exception thrown
    }

    public function test_rejects_a_revisions_sidecar_entry(): void
    {
        // No supported export can produce this path; a zip carrying one is
        // malformed by definition and must be rejected before extraction.
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $zip->addFromString('data/books/1-book/acts/1-act-one/revisions/contents.json', '[]');
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('unexpected file "data/books/1-book/acts/1-act-one/revisions/contents.json"');

        (new ArchiveValidator)->validate($path);
    }

    public function test_allows_an_entry_slug_that_merely_ends_in_revisions(): void
    {
        // Guard against an over-broad match: "revisions" is a path SEGMENT
        // rule, not a substring one.
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $zip->addFromString('data/books/1-book/acts/1-act-revisions/', '');
        });

        (new ArchiveValidator)->validate($path);

        $this->addToAssertionCount(1); // no exception thrown
    }

    // ------------------------------------------------------------------
    // Check 4 — the manifest
    // ------------------------------------------------------------------

    public function test_rejects_an_archive_missing_the_manifest(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $zip->addFromString('data/project/project.json', json_encode(['id' => 1, 'name' => 'P']));
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('missing data/manifest.json');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_an_unsupported_manifest_version(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip, manifestOverrides: ['version' => 999]);
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('export format version "999"');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_a_version_1_archive(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip, manifestOverrides: ['version' => 1]);
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('export format version "1"');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_a_version_2_archive(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip, manifestOverrides: ['version' => 2]);
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('export format version "2"');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_a_version_3_archive(): void
    {
        // Version 3 kept the manuscript at data/acts/ and one publication
        // setting per project. There is no migration path: an archive claiming
        // it is rejected on the manifest version alone.
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip, manifestOverrides: ['version' => 3]);
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('export format version "3"');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_the_previous_layouts_manuscript_path(): void
    {
        // data/acts/ left the allow-list with the layout, so a hand-edited
        // manifest cannot smuggle the old tree back in.
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $zip->addFromString('data/acts/1-act-one/act.json', '{}');
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('unexpected file "data/acts/1-act-one/act.json"');

        (new ArchiveValidator)->validate($path);
    }

    // ------------------------------------------------------------------
    // Check 5 — JSON descriptor shapes
    // ------------------------------------------------------------------

    public function test_rejects_an_archive_without_a_project_descriptor(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $zip->addFromString('data/manifest.json', json_encode($this->manifest()));
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('missing the required descriptor "data/project/project.json"');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_a_malformed_json_descriptor(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $zip->addFromString('data/books/1-book/acts/1-act-one/act.json', '{this is not json');
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('"data/books/1-book/acts/1-act-one/act.json" is not valid JSON');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_a_descriptor_missing_a_required_key(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            // A scene.json without `position` — position replay (a binding
            // import invariant) would be impossible.
            $zip->addFromString(
                'data/books/1-book/acts/1-act-one/chapters/1-chapter-one/scenes/1-scene-one/scene.json',
                json_encode([
                    'id' => 1,
                    'name' => 'Scene one',
                    'status' => 'draft',
                    'chapter_id' => 1,
                    'event_id' => null,
                    'mentioned_event_ids' => [],
                ])
            );
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('missing the required "position" field');

        (new ArchiveValidator)->validate($path);
    }

    // ------------------------------------------------------------------
    // Check 6 — content-sniffed media
    // ------------------------------------------------------------------

    public function test_rejects_a_media_file_whose_content_does_not_match_its_declared_type(): void
    {
        // A renamed PHP file masquerading as a jpeg cover — the declared
        // mime/extension say image, the bytes say executable.
        $phpBytes = '<?php echo "definitely not a jpeg";';

        $path = $this->buildZip(function (ZipArchive $zip) use ($phpBytes): void {
            $this->addValidBaseline($zip);
            $this->addCodexEntryWithMedia($zip, [
                'collection' => 'cover',
                'original_name' => 'portrait.jpg',
                'mime_type' => 'image/jpeg',
                'size' => strlen($phpBytes),
                'file' => 'cover/portrait.jpg',
            ]);
            $zip->addFromString('data/codex/character/1-alice/cover/portrait.jpg', $phpBytes);
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('is not the type of file it claims to be');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_a_media_file_whose_size_does_not_match_the_declared_size(): void
    {
        $pngBytes = base64_decode(self::TINY_PNG_BASE64);

        $path = $this->buildZip(function (ZipArchive $zip) use ($pngBytes): void {
            $this->addValidBaseline($zip);
            $this->addCodexEntryWithMedia($zip, [
                'collection' => 'cover',
                'original_name' => 'portrait.png',
                'mime_type' => 'image/png',
                'size' => strlen($pngBytes) + 1_000_000, // lies about what it ships
                'file' => 'cover/portrait.png',
            ]);
            $zip->addFromString('data/codex/character/1-alice/cover/portrait.png', $pngBytes);
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('does not match the size declared');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_a_missing_media_file_when_the_manifest_includes_media(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip, manifestOverrides: ['includes_media' => true]);
            $this->addCodexEntryWithMedia($zip, [
                'collection' => 'cover',
                'original_name' => 'portrait.png',
                'mime_type' => 'image/png',
                'size' => 68,
                'file' => 'cover/portrait.png',
            ]);
            // ...but no bytes at data/codex/character/1-alice/cover/portrait.png.
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('missing from the archive');

        (new ArchiveValidator)->validate($path);
    }

    public function test_accepts_a_metadata_only_archive_whose_media_bytes_are_absent(): void
    {
        // The "Include images & files" toggle off: media metadata is declared,
        // bytes are legitimately absent — that must validate.
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip, manifestOverrides: ['includes_media' => false]);
            $this->addCodexEntryWithMedia($zip, [
                'collection' => 'cover',
                'original_name' => 'portrait.png',
                'mime_type' => 'image/png',
                'size' => 68,
                'file' => 'cover/portrait.png',
            ]);
        });

        (new ArchiveValidator)->validate($path);

        $this->addToAssertionCount(1); // no exception thrown
    }

    public function test_rejects_a_declared_media_path_that_escapes_the_entry_directory(): void
    {
        // The `file` value inside entry.json is attacker-controlled JSON and
        // gets the same traversal treatment as a real zip entry name.
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $this->addCodexEntryWithMedia($zip, [
                'collection' => 'cover',
                'original_name' => 'portrait.png',
                'mime_type' => 'image/png',
                'size' => 68,
                'file' => '../../../../evil.png',
            ]);
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('unsafe path');

        (new ArchiveValidator)->validate($path);
    }

    // ------------------------------------------------------------------
    // Check 6 (plain cover columns) — content-sniffed like codex media
    // ------------------------------------------------------------------

    public function test_rejects_a_book_cover_whose_content_is_not_an_image(): void
    {
        // A book's cover_file is a plain path column with no declared mime, so
        // it is judged on its bytes alone — exactly like a chapter's.
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $zip->addFromString('data/books/1-book/book.json', json_encode([
                'id' => 1, 'name' => null, 'position' => 1, 'project_id' => 1,
                'cover_file' => 'cover/front.jpg',
            ]));
            $zip->addFromString('data/books/1-book/cover/front.jpg', '<?php echo "definitely not a jpeg";');
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('is not the type of file it claims to be');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_a_project_cover_whose_content_is_not_an_image(): void
    {
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $zip->addFromString('data/manifest.json', json_encode($this->manifest()));
            $zip->addFromString('data/project/project.json', json_encode([
                'id' => 1, 'name' => 'Fixture project', 'cover_file' => 'cover/card.jpg',
            ]));
            $zip->addFromString('data/project/cover/card.jpg', '<?php echo "definitely not a jpeg";');
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('is not the type of file it claims to be');

        (new ArchiveValidator)->validate($path);
    }

    public function test_rejects_a_chapter_cover_whose_content_is_not_an_image(): void
    {
        // A renamed PHP file masquerading as a jpeg chapter cover — the bytes
        // sniff as executable, so the archive is rejected.
        $phpBytes = '<?php echo "definitely not a jpeg";';

        $path = $this->buildZip(function (ZipArchive $zip) use ($phpBytes): void {
            $this->addValidBaseline($zip);
            $this->addChapterWithCover($zip, 'cover/portrait.jpg');
            $zip->addFromString(self::CHAPTER_DIR.'/cover/portrait.jpg', $phpBytes);
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('is not the type of file it claims to be');

        (new ArchiveValidator)->validate($path);
    }

    public function test_accepts_a_chapter_cover_that_is_a_genuine_image(): void
    {
        $pngBytes = base64_decode(self::TINY_PNG_BASE64);

        $path = $this->buildZip(function (ZipArchive $zip) use ($pngBytes): void {
            $this->addValidBaseline($zip);
            $this->addChapterWithCover($zip, 'cover/portrait.png');
            $zip->addFromString(self::CHAPTER_DIR.'/cover/portrait.png', $pngBytes);
        });

        (new ArchiveValidator)->validate($path);

        $this->addToAssertionCount(1); // no exception thrown
    }

    public function test_accepts_a_metadata_only_archive_whose_chapter_cover_bytes_are_absent(): void
    {
        // The "Include images & files" toggle off: chapter.json declares the
        // cover_file link but ships no bytes — that must validate.
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip, manifestOverrides: ['includes_media' => false]);
            $this->addChapterWithCover($zip, 'cover/portrait.png');
        });

        (new ArchiveValidator)->validate($path);

        $this->addToAssertionCount(1); // no exception thrown
    }

    public function test_rejects_a_chapter_cover_path_that_escapes_the_chapter_directory(): void
    {
        // The `cover_file` value inside chapter.json is attacker-controlled JSON
        // and gets the same traversal treatment as a real zip entry name.
        $path = $this->buildZip(function (ZipArchive $zip): void {
            $this->addValidBaseline($zip);
            $this->addChapterWithCover($zip, '../../../../evil.png');
        });

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('unsafe path');

        (new ArchiveValidator)->validate($path);
    }

    // ------------------------------------------------------------------
    // Happy path — a real export validates cleanly (the first point where the
    // export and import code paths touch).
    // ------------------------------------------------------------------

    public function test_accepts_a_real_export_archive_with_media(): void
    {
        $path = $this->exportSeededProject(includeMedia: true);

        (new ArchiveValidator)->validate($path);

        $this->addToAssertionCount(1); // no exception thrown

        unlink($path);
    }

    public function test_accepts_a_real_export_archive_without_media_bytes(): void
    {
        $path = $this->exportSeededProject(includeMedia: false);

        (new ArchiveValidator)->validate($path);

        $this->addToAssertionCount(1); // no exception thrown

        unlink($path);
    }

    // ------------------------------------------------------------------
    // Fixture helpers
    // ------------------------------------------------------------------

    /**
     * Build a fixture zip in the scratch directory and return its path.
     */
    private function buildZip(callable $build): string
    {
        $path = $this->scratchDirectory.DIRECTORY_SEPARATOR.uniqid('fixture-').'.zip';

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $build($zip);
        $zip->close();

        return $path;
    }

    /**
     * The smallest valid archive: a manifest plus the project descriptor.
     *
     * @param  array<string, mixed>  $manifestOverrides
     */
    private function addValidBaseline(ZipArchive $zip, array $manifestOverrides = []): void
    {
        $zip->addFromString('data/manifest.json', json_encode($this->manifest($manifestOverrides)));
        $zip->addFromString('data/project/project.json', json_encode(['id' => 1, 'name' => 'Fixture project']));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function manifest(array $overrides = []): array
    {
        return array_merge([
            'version' => 4,
            'project_id' => 1,
            'exported_at' => '2026-07-13T00:00:00+00:00',
            'includes_media' => true,
        ], $overrides);
    }

    /**
     * Add a shape-valid chapter.json under the fixture book, whose `cover_file`
     * links the given relative path. The cover BYTES are the caller's business.
     */
    private function addChapterWithCover(ZipArchive $zip, string $coverFile): void
    {
        $zip->addFromString(self::CHAPTER_DIR.'/chapter.json', json_encode([
            'id' => 1,
            'name' => 'Chapter one',
            'position' => 1,
            'act_id' => 1,
            'cover_file' => $coverFile,
        ]));
    }

    /**
     * Add a shape-valid entry.json (at data/codex/character/1-alice/) declaring
     * one media item. The media BYTES are the caller's business.
     *
     * @param  array<string, mixed>  $media
     */
    private function addCodexEntryWithMedia(ZipArchive $zip, array $media): void
    {
        $zip->addFromString('data/codex/character/1-alice/entry.json', json_encode([
            'id' => 1,
            'name' => 'Alice',
            'type' => 'character',
            'project_id' => 1,
            'aliases' => [],
            'tag_ids' => [],
            'attribute_values' => [],
            'media' => [array_merge(['id' => 1, 'position' => 1], $media)],
        ]));
    }

    /**
     * Seed a small but complete project graph (story tree, timeline, codex entry
     * with alias/tag/attribute-value/cover media) and export it for real via
     * StaticSiteExporter, returning the zip path.
     */
    private function exportSeededProject(bool $includeMedia): string
    {
        Storage::fake('public');

        // Genuine png bytes behind every cover, so the export copies real
        // images for the validator to content-sniff.
        $pngBytes = base64_decode(self::TINY_PNG_BASE64);
        Storage::disk('public')->put('project-covers/card.png', $pngBytes);
        Storage::disk('public')->put('book-covers/front.png', $pngBytes);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'Round-trip project',
            'cover_image' => 'project-covers/card.png',
        ]);
        $book = $project->books()->first();
        $book->update(['cover_image' => 'book-covers/front.png']);

        $act = Act::factory()->for($book)->create(['name' => 'Act one']);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Chapter one']);
        Scene::factory()->for($chapter)->create(['name' => 'Scene one', 'contents' => 'Some *prose*.']);

        $plotline = Plotline::factory()->for($project)->create(['name' => 'Side quest']);
        $event = Event::factory()->for($project)->create(['title' => 'The reveal']);
        $event->plotlines()->attach($plotline);

        $entry = CodexEntry::factory()->for($project)->create(['name' => 'Alice Harker']);
        CodexAlias::factory()->for($entry, 'entry')->create(['alias' => 'Ally']);
        $tag = Tag::factory()->for($project)->create(['name' => 'protagonist']);
        $entry->tags()->attach($tag);

        $attribute = CodexAttribute::factory()->for($project)->create(['name' => 'Age']);
        CodexAttributeValue::factory()
            ->for($entry, 'entry')
            ->startingAt($event)
            ->create(['codex_attribute_id' => $attribute->id, 'value' => '29']);

        Storage::disk('public')->put('codex-media/portrait.png', $pngBytes);
        CodexMedia::factory()->cover()->for($entry, 'entry')->create([
            'path' => 'codex-media/portrait.png',
            'original_name' => 'portrait.png',
            'mime_type' => 'image/png',
            'size' => strlen($pngBytes),
        ]);

        return (new StaticSiteExporter)->export($project->fresh(), $includeMedia);
    }
}
