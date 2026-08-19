<?php

namespace App\Services;

use App\Enums\ChapterTitleFormat;
use App\Exceptions\EpubExportException;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\PublicationSetting;
use App\Models\Scene;
use App\Support\Markdown\StrikethroughSExtension;
use App\Support\RichText;
use App\Support\StoryNumbering;
use DOMDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use Rampmaster\EPub\Core\EPub;
use Rampmaster\EPub\Core\Structure\OPF\DublinCore;
use RuntimeException;
use ZipArchive;

/**
 * Builds one book's EPUB 3 file.
 *
 * Metadata, covers, matter pages, and publication settings belong to the book.
 * Appendix entries come from the project codex. Only entries referenced by this
 * book can appear in its appendix.
 *
 * The EPUB library builds the package, OPF, spine, and navigation document. This
 * service supplies metadata, CSS, XHTML views, and the story navigation tree.
 * {@see addSections()} applies the configured section order.
 *
 * Navigation can stop at acts, chapters, or scenes. Act depth combines each act
 * and its chapters on one page. Scene depth links to anchors in chapter pages.
 * A chapter cover is a sibling of its chapter in the navigation tree.
 *
 * An empty book causes {@see EpubExportException}. Invalid generated XML or OPF
 * content causes a RuntimeException because it is a generator error.
 *
 * > [!WARNING]
 * > Use this service's private SmartPunct converter for scene Markdown. Do not
 * > change the shared scene renderer.
 * >
 * > Render EPUB HTML through resources/views/exports/epub/. Do not build it here.
 */
class EpubExporter
{
    public function __construct(private CoverImageService $coverImageService) {}

    /** The shared stylesheet name in the package and Blade layout. */
    public const STYLESHEET_FILENAME = 'styles.css';

    /** The fallback BCP-47 language code. */
    private const DEFAULT_LANGUAGE = 'en';

    /** EPUB 3 supports XHTML5, navigation, and accessibility metadata. */
    private const EPUB_VERSION = EPub::BOOK_VERSION_EPUB3;

    /** Accessibility metadata for a text publication with a table of contents. */
    private const ACCESSIBILITY_SUMMARY = 'Text-only publication with structural navigation via table of contents.';

    private const ACCESS_MODE = 'textual';

    private const ACCESSIBILITY_FEATURE = 'structuralNavigation';

    /** The library's fixed OPF path inside the package. */
    private const OPF_ENTRY = 'OEBPS/book.opf';

    /** The vendored EPUB 3 OPF RelaxNG schema, relative to resource_path(). */
    private const OPF_SCHEMA = 'epub-schemas/package-30.rng';

    /** Filenames for the readable title and contents pages in the spine. */
    private const TITLE_FILE = 'title.xhtml';

    private const TOC_FILE = 'toc.xhtml';

    /** Filename and heading for the appendix root page. */
    private const APPENDIX_FILE = 'appendix.xhtml';

    private const APPENDIX_HEADING = 'Appendix';

    /**
     * Maps each Markdown matter section to its field, toggle, heading, and file.
     *
     * @var array<string, array{field: string, toggle: string, heading: string, file: string}>
     */
    private const MATTER_SECTIONS = [
        'dedication' => [
            'field' => 'dedication',
            'toggle' => 'include_dedication',
            'heading' => 'Dedication',
            'file' => 'dedication.xhtml',
        ],
        'acknowledgements' => [
            'field' => 'acknowledgements',
            'toggle' => 'include_acknowledgements',
            'heading' => 'Acknowledgements',
            'file' => 'acknowledgements.xhtml',
        ],
        'preface' => [
            'field' => 'preface',
            'toggle' => 'include_preface',
            'heading' => 'Preface',
            'file' => 'preface.xhtml',
        ],
        'postface' => [
            'field' => 'postface',
            'toggle' => 'include_postface',
            'heading' => 'Postface',
            'file' => 'postface.xhtml',
        ],
    ];

    /**
     * Builds a temporary EPUB and returns its path.
     *
     * A failed export removes its partial file.
     */
    public function export(Book $book): string
    {
        $tree = $this->actTree($book);

        // Reject an empty book before this method creates a temporary file.
        if (! $this->hasAnyScene($tree)) {
            throw EpubExportException::nothingToExport();
        }

        // A book without saved settings uses the model's default settings.
        $settings = $book->publicationSettingOrDefault();

        // Use one numbering map for all pages and navigation labels.
        $numbering = StoryNumbering::fromActs($tree);

        $epub = new EPub(self::EPUB_VERSION, $this->language($book));

        $this->applyMetadata($epub, $book, $settings);
        $this->applyCover($epub, $book, $settings);
        $epub->addCSSFile(self::STYLESHEET_FILENAME, 'epub-styles', $this->stylesheet());
        $this->addSections($epub, $book, $tree, $settings, $numbering);

        $path = $this->freshTempEpubPath();

        try {
            // Control the temporary path so concurrent exports cannot share a file.
            $bytes = $epub->getBook();

            if (file_put_contents($path, $bytes) === false) {
                throw new RuntimeException("Unable to write the generated epub to {$path}.");
            }

            // Validate after OPF corrections so validation covers the final package.
            $this->normalizeOpf($path, $book);
            $this->validatePackage($path);
        } catch (\Throwable $e) {
            if (is_file($path)) {
                unlink($path);
            }

            throw $e;
        }

        return $path;
    }

    /** The private SmartPunct converter for this export. */
    private ?CommonMarkConverter $converter = null;

    /**
     * Loads the complete ordered story tree.
     *
     * Empty outline items remain because their removal would change story numbers.
     * {@see hasAnyScene()} determines whether the book has exportable content.
     *
     * @return Collection<int, Act>
     */
    public function actTree(Book $book): Collection
    {
        return $book->acts()
            ->with([
                'chapters' => fn ($query) => $query->orderBy('position'),
                'chapters.scenes' => fn ($query) => $query->orderBy('position'),
            ])
            ->orderBy('position')
            ->get();
    }

    /** @param Collection<int, Act> $tree */
    private function hasAnyScene(Collection $tree): bool
    {
        return $tree->contains(
            fn (Act $act) => $act->chapters->contains(
                fn (Chapter $chapter) => $chapter->scenes->isNotEmpty()
            )
        );
    }

    /** Renders an act divider and its optional rich-text description. */
    public function renderAct(Act $act, Book $book, ?PublicationSetting $settings = null, ?StoryNumbering $numbering = null): string
    {
        $settings ??= $book->publicationSettingOrDefault();
        $numbering ??= StoryNumbering::forBook($book);

        return view('exports.epub.act', $this->actViewData($act, $book, $settings, $numbering))->render();
    }

    /** Combines an act and its chapters when navigation stops at acts. */
    public function renderActWithChapters(Act $act, Book $book, ?PublicationSetting $settings = null, ?StoryNumbering $numbering = null): string
    {
        $settings ??= $book->publicationSettingOrDefault();
        $numbering ??= StoryNumbering::forBook($book);

        return view('exports.epub.act-combined', array_merge(
            $this->actViewData($act, $book, $settings, $numbering),
            [
                'chapters' => $act->chapters
                    ->map(fn (Chapter $chapter) => $this->chapterViewData($chapter, $book, $settings, $numbering))
                    ->all(),
            ]
        ))->render();
    }

    /**
     * Uses the gap-free story number instead of the database position.
     *
     * @return array<string, mixed>
     */
    private function actViewData(Act $act, Book $book, PublicationSetting $settings, StoryNumbering $numbering): array
    {
        return [
            'number' => $numbering->act($act),
            'name' => $act->name,
            'showDescription' => $settings->include_act_descriptions,
            'description' => RichText::toXhtmlFragment($act->description),
            'language' => $this->language($book),
        ];
    }

    /**
     * Renders one chapter and its ordered scenes.
     *
     * Settings can add descriptions, scene titles, and scene dividers. An empty
     * chapter renders only its heading.
     */
    public function renderChapter(Chapter $chapter, Book $book, ?PublicationSetting $settings = null, ?StoryNumbering $numbering = null): string
    {
        $settings ??= $book->publicationSettingOrDefault();
        $numbering ??= StoryNumbering::forBook($book);

        return view('exports.epub.chapter', $this->chapterViewData($chapter, $book, $settings, $numbering))->render();
    }

    /**
     * Adds stable scene anchors only when scene-level navigation uses them.
     * Uses the gap-free story number instead of the database position.
     *
     * @return array<string, mixed>
     */
    private function chapterViewData(Chapter $chapter, Book $book, PublicationSetting $settings, StoryNumbering $numbering): array
    {
        $scenes = $chapter->scenes->map(fn (Scene $scene): array => [
            'id' => $scene->id,
            'title' => trim($scene->name ?? ''),
            'description' => RichText::toXhtmlFragment($scene->description),
            'body' => $this->renderSceneContents($scene),
        ])->all();

        $number = $numbering->chapter($chapter);

        return [
            'heading' => $settings->chapter_title_format->format($number, $chapter->name),
            'number' => $number,
            'showChapterDescription' => $settings->include_chapter_descriptions,
            'chapterDescription' => RichText::toXhtmlFragment($chapter->description),
            'showSceneTitles' => $settings->include_scene_titles,
            'showSceneDescriptions' => $settings->include_scene_descriptions,
            'sceneAnchors' => $settings->table_of_contents_depth->includesScenes(),
            'dividerHtml' => $settings->divider_type->dividerHtml(),
            'scenes' => $scenes,
            'language' => $this->language($book),
        ];
    }

    /** Renders the title page with the book's display name. */
    public function renderTitlePage(Book $book): string
    {
        return view('exports.epub.title', [
            'name' => $book->displayName(),
            'language' => $this->language($book),
        ])->render();
    }

    /**
     * Renders the readable contents page in the spine.
     *
     * @param  Collection<int, Act>  $tree
     */
    public function renderToc(Book $book, Collection $tree, ?PublicationSetting $settings = null, ?StoryNumbering $numbering = null): string
    {
        $settings ??= $book->publicationSettingOrDefault();
        // Build numbering from the loaded tree without another query.
        $numbering ??= StoryNumbering::fromActs($tree);
        $format = $settings->chapter_title_format;
        $depth = $settings->table_of_contents_depth;

        // Empty child arrays stop the view at the configured navigation depth.
        $entries = $tree->map(fn (Act $act) => [
            'href' => $this->actFileName($act),
            'label' => $this->actNavTitle($act, $numbering),
            'chapters' => $depth->includesChapters()
                ? $act->chapters->map(fn (Chapter $chapter) => [
                    'href' => $this->chapterFileName($chapter),
                    'label' => $this->chapterNavTitle($chapter, $format, $numbering),
                    'scenes' => $depth->includesScenes()
                        ? $chapter->scenes->map(fn (Scene $scene) => [
                            'href' => $this->sceneAnchorHref($chapter, $scene),
                            'label' => $this->sceneNavTitle($scene),
                        ])->all()
                        : [],
                ])->all()
                : [],
        ])->all();

        return view('exports.epub.toc', [
            'entries' => $entries,
            'language' => $this->language($book),
        ])->render();
    }

    /** Returns the stylesheet stored under {@see STYLESHEET_FILENAME}. */
    public function stylesheet(): string
    {
        return file_get_contents(resource_path('views/exports/epub/styles.css'));
    }

    /**
     * Adds enabled, non-empty book metadata.
     *
     * The stable book URN remains the primary identifier. An ISBN adds a second
     * identifier. Title, language, the URN, and accessibility data are required.
     */
    private function applyMetadata(EPub $epub, Book $book, PublicationSetting $settings): void
    {
        $epub->setTitle($book->displayName());
        $epub->setLanguage($this->language($book));
        $epub->setIdentifier($this->primaryIdentifier($book), EPub::IDENTIFIER_URI);

        if ($settings->include_author && filled($book->author)) {
            $epub->setAuthor($book->author, $book->author);
        }

        if ($settings->include_publisher && filled($book->publisher)) {
            // The app stores no publisher URL.
            $epub->setPublisher($book->publisher, '');
        }

        if ($settings->include_rights && filled($book->rights)) {
            $epub->setRights($book->rights);
        }

        if ($settings->include_isbn && filled($book->isbn)) {
            // EPUB 3 expresses the ISBN scheme in the identifier URI.
            $epub->addCustomMetaValue(new DublinCore(DublinCore::IDENTIFIER, 'urn:isbn:'.$book->isbn));
        }

        // Use the library API so accessibility data stays consistent with the package.
        $epub->setAccessibilitySummary(self::ACCESSIBILITY_SUMMARY);
        $epub->addAccessMode(self::ACCESS_MODE);
        $epub->addAccessibilityFeature(self::ACCESSIBILITY_FEATURE);
    }

    /**
     * Adds an enabled book cover from the public disk through the library cover API.
     *
     * A missing file does not fail the export. The export does not need a storage link.
     */
    private function applyCover(EPub $epub, Book $book, PublicationSetting $settings): void
    {
        if (! $settings->include_book_cover || blank($book->cover_image)) {
            return;
        }

        $bytes = $this->coverImageService->bytes($book->cover_image);
        if ($bytes === null) {
            return;
        }

        // The library can derive the MIME type from the extension when needed.
        $epub->setCoverImage(
            basename($book->cover_image),
            $bytes,
            $this->coverImageService->mimeType($book->cover_image) ?: null
        );
    }

    /**
     * Adds enabled, non-empty sections in the configured order.
     *
     * The model owns title-page pinning. This method uses the order as supplied.
     *
     * @param  Collection<int, Act>  $tree
     */
    private function addSections(EPub $epub, Book $book, Collection $tree, PublicationSetting $settings, StoryNumbering $numbering): void
    {
        $order = $settings->section_order ?? PublicationSetting::SECTION_KEYS;

        foreach ($order as $section) {
            match ($section) {
                'title' => $this->addTitleSection($epub, $book),
                'dedication', 'acknowledgements', 'preface', 'postface' => $this->addMatterSection($epub, $book, $settings, $section),
                'toc' => $this->addTocSection($epub, $book, $tree, $settings, $numbering),
                'body' => $this->addBody($epub, $book, $tree, $settings, $numbering),
                'appendix' => $this->addAppendixSection($epub, $book, $settings),
                default => null,
            };
        }
    }

    /** Adds the title page as a root navigation entry. */
    private function addTitleSection(EPub $epub, Book $book): void
    {
        $titleXhtml = $this->renderTitlePage($book);
        $this->assertXmlWellFormed($titleXhtml, self::TITLE_FILE);
        $epub->addChapter($book->displayName(), self::TITLE_FILE, $titleXhtml);
    }

    /** @param Collection<int, Act> $tree */
    private function addTocSection(EPub $epub, Book $book, Collection $tree, PublicationSetting $settings, StoryNumbering $numbering): void
    {
        $tocXhtml = $this->renderToc($book, $tree, $settings, $numbering);
        $this->assertXmlWellFormed($tocXhtml, self::TOC_FILE);
        $epub->addChapter('Table of Contents', self::TOC_FILE, $tocXhtml);
    }

    /** Adds enabled, non-empty Markdown matter as a root navigation entry. */
    private function addMatterSection(EPub $epub, Book $book, PublicationSetting $settings, string $key): void
    {
        $config = self::MATTER_SECTIONS[$key] ?? null;
        if ($config === null) {
            return;
        }

        if (! $settings->{$config['toggle']}) {
            return;
        }

        $markdown = $book->{$config['field']};
        if (blank($markdown)) {
            return;
        }

        $xhtml = $this->renderMatterPage($config['heading'], $markdown, $book);
        $this->assertXmlWellFormed($xhtml, $config['file']);
        $epub->addChapter($config['heading'], $config['file'], $xhtml);
    }

    /** Renders book matter with the same private Markdown converter as scenes. */
    public function renderMatterPage(string $heading, string $markdown, Book $book): string
    {
        return view('exports.epub.matter', [
            'heading' => $heading,
            'body' => (string) $this->converter()->convert($markdown),
            'language' => $this->language($book),
        ])->render();
    }

    /** Renders the root page for appendix entries. */
    public function renderAppendixHeading(Book $book): string
    {
        return view('exports.epub.appendix', [
            'heading' => self::APPENDIX_HEADING,
            'language' => $this->language($book),
        ])->render();
    }

    /**
     * Renders one appendix entry as XHTML, with its optional packaged image.
     *
     * Codex descriptions are rich HTML, not Markdown.
     */
    public function renderAppendixEntry(CodexEntry $entry, Book $book, ?string $imagePath = null): string
    {
        return view('exports.epub.appendix-entry', [
            'name' => $entry->name,
            'imagePath' => $imagePath,
            'description' => RichText::toXhtmlFragment($entry->description),
            'language' => $this->language($book),
        ])->render();
    }

    /**
     * Adds a root appendix page and one child page per selected codex entry.
     *
     * The appendix is absent when it is disabled or has no matching entries.
     * Entries must have references from this book. This prevents content from
     * another book from appearing as a spoiler. Entries sort by type and name.
     * Media loads only when appendix images are enabled.
     */
    private function addAppendixSection(EPub $epub, Book $book, PublicationSetting $settings): void
    {
        if (! $settings->include_codex_appendix) {
            return;
        }

        $types = $settings->appendix_entry_types ?? [];
        if ($types === []) {
            return;
        }

        $query = $book->project->codexEntries()
            ->whereIn('type', $types)
            ->whereHas('referencingScenes', fn (Builder $query) => $query->whereIn(
                'scenes.id', $book->sceneQuery()->select('scenes.id')
            ))
            ->orderBy('type')
            ->orderBy('name');

        // Ordered media makes the selected first image stable.
        if ($settings->appendix_include_images) {
            $query->with(['media' => fn ($relation) => $relation->orderBy('collection')->orderBy('position')]);
        }

        $entries = $query->get();

        if ($entries->isEmpty()) {
            return;
        }

        $headingXhtml = $this->renderAppendixHeading($book);
        $this->assertXmlWellFormed($headingXhtml, self::APPENDIX_FILE);
        $epub->addChapter(self::APPENDIX_HEADING, self::APPENDIX_FILE, $headingXhtml);

        $epub->subLevel();

        foreach ($entries as $entry) {
            $imagePath = $settings->appendix_include_images
                ? $this->addAppendixEntryImage($epub, $entry)
                : null;

            $entryFile = $this->appendixEntryFileName($entry);
            $entryXhtml = $this->renderAppendixEntry($entry, $book, $imagePath);
            $this->assertXmlWellFormed($entryXhtml, $entryFile);
            $epub->addChapter($entry->name, $entryFile, $entryXhtml);
        }

        $epub->backLevel();
    }

    /**
     * Packages the first available image and returns its unique package path.
     *
     * It skips non-images, missing metadata, and missing files. A missing image
     * does not fail the export. The image has no spine or navigation entry.
     */
    private function addAppendixEntryImage(EPub $epub, CodexEntry $entry): ?string
    {
        $image = $entry->media->first(
            fn (CodexMedia $media) => $media->path !== null && str_starts_with((string) $media->mime_type, 'image/')
        );

        if ($image === null) {
            return null;
        }

        $bytes = $this->coverImageService->bytes($image->path);
        if ($bytes === null) {
            return null;
        }

        $imagePath = 'images/appendix-entry-'.$entry->id.'-'.basename($image->path);
        $mimeType = $image->mime_type
            ?: ($this->coverImageService->mimeType($image->path) ?: 'application/octet-stream');

        $epub->addFile($imagePath, 'appendix_image_'.$entry->id, $bytes, $mimeType);

        return $imagePath;
    }

    /**
     * Adds the ordered story pages and their configured navigation levels.
     *
     * @param  Collection<int, Act>  $tree
     */
    private function addBody(EPub $epub, Book $book, Collection $tree, PublicationSetting $settings, StoryNumbering $numbering): void
    {
        $format = $settings->chapter_title_format;
        $depth = $settings->table_of_contents_depth;

        foreach ($tree as $act) {
            $actFile = $this->actFileName($act);

            // The library cannot hide spine pages from EPUB 3 navigation. Combine each act.
            if (! $depth->includesChapters()) {
                foreach ($act->chapters as $chapter) {
                    $this->addChapterCoverPage($epub, $chapter, $book, $settings);
                }

                $actXhtml = $this->renderActWithChapters($act, $book, $settings, $numbering);
                $this->assertXmlWellFormed($actXhtml, $actFile);
                $epub->addChapter($this->actNavTitle($act, $numbering), $actFile, $actXhtml);

                continue;
            }

            // Validate each act page before it enters the package.
            $actXhtml = $this->renderAct($act, $book, $settings, $numbering);
            $this->assertXmlWellFormed($actXhtml, $actFile);
            $epub->addChapter($this->actNavTitle($act, $numbering), $actFile, $actXhtml);

            $epub->subLevel();

            foreach ($act->chapters as $chapter) {
                // A cover is the chapter's sibling so scene links remain its children.
                $this->addChapterCoverPage($epub, $chapter, $book, $settings);

                $chapterFile = $this->chapterFileName($chapter);
                $chapterXhtml = $this->renderChapter($chapter, $book, $settings, $numbering);
                $this->assertXmlWellFormed($chapterXhtml, $chapterFile);
                $epub->addChapter($this->chapterNavTitle($chapter, $format, $numbering), $chapterFile, $chapterXhtml);

                // Null content adds an anchor link without another spine page.
                if ($depth->includesScenes()) {
                    $epub->subLevel();

                    foreach ($chapter->scenes as $scene) {
                        $epub->addChapter(
                            $this->sceneNavTitle($scene),
                            $this->sceneAnchorHref($chapter, $scene),
                            null
                        );
                    }

                    $epub->backLevel();
                }
            }

            $epub->backLevel();
        }
    }

    /**
     * Adds a chapter cover page before its content.
     *
     * A disabled, absent, or missing cover is a no-op. The package path includes
     * the chapter ID to prevent collisions. The book cover remains the package cover.
     */
    private function addChapterCoverPage(EPub $epub, Chapter $chapter, Book $book, PublicationSetting $settings): void
    {
        if (! $settings->include_chapter_covers || blank($chapter->cover_image)) {
            return;
        }

        $bytes = $this->coverImageService->bytes($chapter->cover_image);
        if ($bytes === null) {
            return;
        }

        $imagePath = 'images/chapter-cover-'.$chapter->id.'-'.basename($chapter->cover_image);
        $mimeType = $this->coverImageService->mimeType($chapter->cover_image) ?: 'application/octet-stream';

        $coverXhtml = view('exports.epub.chapter-cover', [
            'title' => 'Cover',
            'imagePath' => $imagePath,
            'language' => $this->language($book),
        ])->render();
        $coverFile = $this->chapterCoverFileName($chapter);
        $this->assertXmlWellFormed($coverXhtml, $coverFile);

        $epub->addFile($imagePath, 'chapter_cover_'.$chapter->id, $bytes, $mimeType);
        $epub->addChapter('Cover', $coverFile, $coverXhtml);
    }

    /**
     * Corrects language and source metadata after the library finalizes the OPF.
     *
     * The library rejects region-tagged languages. It also derives the source from
     * the request and can create an invalid CLI URL. Use the book language and the
     * configured app URL so HTTP and queue exports match.
     */
    private function normalizeOpf(string $epubPath, Book $book): void
    {
        $zip = new ZipArchive;
        if ($zip->open($epubPath) !== true) {
            throw new RuntimeException("Unable to reopen the generated epub at {$epubPath}.");
        }

        try {
            $opf = $zip->getFromName(self::OPF_ENTRY);
            if ($opf === false) {
                return;
            }

            $opf = preg_replace(
                '#<dc:language>.*?</dc:language>#',
                '<dc:language>'.$this->escapeXmlText($this->language($book)).'</dc:language>',
                $opf,
                1
            );

            $opf = preg_replace(
                '#<dc:source>.*?</dc:source>#',
                '<dc:source>'.$this->escapeXmlText((string) config('app.url')).'</dc:source>',
                $opf,
                1
            );

            $zip->addFromString(self::OPF_ENTRY, $opf);
        } finally {
            $zip->close();
        }
    }

    /**
     * Validates all XHTML for well-formed XML and the OPF against EPUB 3 RelaxNG.
     *
     * Libxml cannot process the full navigation grammar. The navigation document
     * therefore receives only the XML check. Any failure is a generator error.
     */
    private function validatePackage(string $epubPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($epubPath) !== true) {
            throw new RuntimeException("Unable to reopen the generated epub at {$epubPath} for validation.");
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);
                if (str_ends_with($entry, '.xhtml')) {
                    $this->assertXmlWellFormed((string) $zip->getFromName($entry), $entry);
                }
            }

            $this->assertOpfMatchesSchema((string) $zip->getFromName(self::OPF_ENTRY));
        } finally {
            $zip->close();
        }
    }

    /** Throws parser details when an XML document is not well-formed. */
    private function assertXmlWellFormed(string $xml, string $context): void
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument;
        $parsed = $document->loadXML($xml);
        $errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($parsed === false || $errors !== []) {
            throw new RuntimeException(
                "Generated epub document {$context} is not well-formed XML: ".$this->formatLibxmlErrors($errors)
            );
        }
    }

    /** Throws validation details when the OPF violates the EPUB 3 schema. */
    private function assertOpfMatchesSchema(string $opf): void
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument;
        $document->loadXML($opf);
        $valid = $document->relaxNGValidate(resource_path(self::OPF_SCHEMA));
        $errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $valid) {
            throw new RuntimeException(
                'Generated OPF package document failed EPUB 3 schema validation: '.$this->formatLibxmlErrors($errors)
            );
        }
    }

    /** @param array<int, \LibXMLError> $errors */
    private function formatLibxmlErrors(array $errors): string
    {
        if ($errors === []) {
            return 'unknown parser error.';
        }

        return implode('; ', array_map(
            fn (\LibXMLError $error): string => trim($error->message)." (line {$error->line})",
            $errors
        ));
    }

    /** Escapes text for XML element content. */
    private function escapeXmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }

    /** Builds an act label with its gap-free story number and optional name. */
    private function actNavTitle(Act $act, StoryNumbering $numbering): string
    {
        $number = $numbering->act($act);

        return filled($act->name)
            ? "Act {$number}: {$act->name}"
            : "Act {$number}";
    }

    /**
     * Builds a chapter label with its gap-free story number.
     *
     * A contents listing cannot use the empty label that Title format can return.
     * Use a numbered fallback only in navigation.
     */
    private function chapterNavTitle(Chapter $chapter, ChapterTitleFormat $format, StoryNumbering $numbering): string
    {
        $number = $numbering->chapter($chapter);
        $label = $format->format($number, $chapter->name);

        return $label !== '' ? $label : "Chapter {$number}";
    }

    /** Uses the scene name or a numbered fallback. */
    private function sceneNavTitle(Scene $scene): string
    {
        return filled($scene->name)
            ? $scene->name
            : "Scene {$scene->position}";
    }

    /** Builds the shared scene anchor link for the contents page and EPUB navigation. */
    private function sceneAnchorHref(Chapter $chapter, Scene $scene): string
    {
        return $this->chapterFileName($chapter).'#scene-'.$scene->id;
    }

    /** Uses the stable act ID to prevent filename collisions. */
    private function actFileName(Act $act): string
    {
        return "act-{$act->id}.xhtml";
    }

    /** Uses the stable chapter ID to prevent filename collisions. */
    private function chapterFileName(Chapter $chapter): string
    {
        return "chapter-{$chapter->id}.xhtml";
    }

    /** Returns the separate filename for a chapter cover page. */
    private function chapterCoverFileName(Chapter $chapter): string
    {
        return "chapter-cover-{$chapter->id}.xhtml";
    }

    /** Uses the stable entry ID to prevent filename collisions. */
    private function appendixEntryFileName(CodexEntry $entry): string
    {
        return "appendix-entry-{$entry->id}.xhtml";
    }

    /** Returns the stable primary identifier for a book. */
    private function primaryIdentifier(Book $book): string
    {
        return "urn:imagoldfish:book:{$book->id}";
    }

    /** Creates a unique path so concurrent exports cannot overwrite each other. */
    private function freshTempEpubPath(): string
    {
        $directory = config('exports.temp_path');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.DIRECTORY_SEPARATOR.Str::uuid().'.epub';
    }

    /** Converts scene Markdown with the EPUB-only SmartPunct converter. */
    private function renderSceneContents(Scene $scene): string
    {
        return (string) $this->converter()->convert($scene->contents ?? '');
    }

    /** Builds one isolated CommonMark converter per export. */
    private function converter(): CommonMarkConverter
    {
        if ($this->converter === null) {
            $converter = new CommonMarkConverter;
            // Smart punctuation must remain isolated from the shared scene renderer.
            $converter->getEnvironment()->addExtension(new SmartPunctExtension);
            // Add the GFM features that scene validation and shared rendering support.
            $converter->getEnvironment()->addExtension(new StrikethroughExtension);
            // Same `<s>` tag as the shared AuthorMarkdown renderer, not `<del>`.
            $converter->getEnvironment()->addExtension(new StrikethroughSExtension);
            $converter->getEnvironment()->addExtension(new TaskListExtension);

            $this->converter = $converter;
        }

        return $this->converter;
    }

    /** Returns the book's BCP-47 code or the fallback language. */
    private function language(Book $book): string
    {
        return $book->language?->value ?? self::DEFAULT_LANGUAGE;
    }
}
