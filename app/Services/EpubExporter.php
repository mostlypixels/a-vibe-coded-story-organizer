<?php

namespace App\Services;

use App\Exceptions\EpubExportException;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use DOMDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use Rampmaster\EPub\Core\EPub;
use Rampmaster\EPub\Core\Structure\OPF\DublinCore;
use RuntimeException;
use ZipArchive;

/**
 * Builds the in-memory content of a project's EPUB export.
 *
 * HTTP-agnostic and analogous to {@see StaticSiteExporter}: it takes a Project and
 * produces the ordered, filtered act/chapter tree, the rendered XHTML content documents,
 * and — via {@see export()} — packages them into an actual .epub file on disk.
 *
 * Task 03 built content generation (loading + filtering + rendering). Task 04 added the
 * packaging step ({@see export()}): the rampmaster/phpepub library owns the mimetype,
 * container.xml, OPF (metadata/manifest/spine) and the EPUB 3 nav document; this service
 * owns the XHTML content (Blade), the metadata values fed to the library, the CSS, and the
 * two-level Act → Chapter navigation. A later follow-up added {@see addFrontMatter()}: a
 * story title page and an in-book table-of-contents page (a real spine page, distinct from
 * the reader-chrome nav document the library builds), both added before any Act/Chapter
 * content so they open the book. Task 05 adds the guards that make a valid package a
 * hard gate:
 *   - An empty filtered tree throws {@see EpubExportException} (a USER problem — nothing to
 *     export), surfaced by the controller as a validation-style error.
 *   - Every shipped XHTML document is checked for XML well-formedness and the OPF is schema-
 *     validated against the vendored EPUB 3 RelaxNG grammar ({@see validatePackage()}). A
 *     failure there is a generator BUG, not user error, so it throws a plain RuntimeException
 *     (let it 500 and be logged) rather than EpubExportException.
 *
 * Two deliberate isolation rules, both grilled decisions:
 *   - Scene contents are rendered through this service's OWN CommonMark converter,
 *     configured with SmartPunctExtension (smart dashes, ellipses, and quotes). It is
 *     never Scene::renderedContents — that accessor is the shared render path for the
 *     Story overview, share page, and book/ export, and must stay byte-for-byte the same.
 *   - HTML is rendered through Blade views under resources/views/exports/epub/, never
 *     string-built here (matching the book/ layer's own rule).
 */
class EpubExporter
{
    /**
     * The stylesheet's flat filename inside the epub package. Task 04 writes the CSS
     * beside the content documents under this name, and the Blade layout links to it by
     * exactly this name — single source of truth so the link and the packaged file can
     * never drift apart.
     */
    public const STYLESHEET_FILENAME = 'styles.css';

    /**
     * The BCP-47 code used when a Project somehow has no `language` (the column is NOT
     * NULL with a DB default of 'en', so this is a belt-and-braces fallback only).
     */
    private const DEFAULT_LANGUAGE = 'en';

    /**
     * The EPUB version the package is built as. EPUB 3 is required: the content documents
     * are XHTML5 with `epub:type`, the nav is an EPUB 3 nav document, and the accessibility
     * metadata is emitted as EPUB 3 `<meta property="schema:...">` entries — none of which
     * EPUB 2 supports.
     */
    private const EPUB_VERSION = EPub::BOOK_VERSION_EPUB3;

    /**
     * Accessibility metadata for a text-only publication navigable by its table of
     * contents. Emitted via the library's native accessibility methods (never hand-written
     * OPF XML) so it always reflects what the package actually is.
     */
    private const ACCESSIBILITY_SUMMARY = 'Text-only publication with structural navigation via table of contents.';

    private const ACCESS_MODE = 'textual';

    private const ACCESSIBILITY_FEATURE = 'structuralNavigation';

    /**
     * The path of the OPF package document inside the generated epub. Fixed by the
     * rampmaster/phpepub library (bookRoot `OEBPS/` + `book.opf`) and referenced by the
     * container.xml it writes — the single place this service needs it, to correct the
     * `dc:language` the library under-reports (see {@see correctOpfLanguage()}).
     */
    private const OPF_ENTRY = 'OEBPS/book.opf';

    /**
     * The vendored EPUB 3 OPF RelaxNG schema (RelaxNG XML syntax), relative to
     * `resource_path()`. Task 05 validates the generated OPF against this. Its two
     * `<include>`d modules (`datatypes.rng`, `epub-prefix-attr.rng`) live beside it and are
     * resolved relative to this file — see resources/epub-schemas/README.md for provenance.
     */
    private const OPF_SCHEMA = 'epub-schemas/package-30.rng';

    /**
     * Filenames for the two front-matter pages added by {@see addFrontMatter()}: the story
     * title page and the in-book table of contents. Distinct from the EPUB 3 nav document
     * (the reader-chrome TOC rampmaster/phpepub builds automatically) — this is a real,
     * readable content page in the spine, placed right after the title page per the
     * grilled decision.
     */
    private const TITLE_FILE = 'title.xhtml';

    private const TOC_FILE = 'toc.xhtml';

    /**
     * Package a Project's filtered/rendered tree into an actual .epub file and return the
     * path to it on disk. The caller (the controller, task 06) streams the file and deletes
     * it after send; the temp file is also removed here if packaging throws, so a failed
     * export never leaks a partial file — mirroring {@see StaticSiteExporter::export()}.
     *
     * The two-level TOC/nav (Acts as parent entries, surviving Chapters nested underneath,
     * both in `position` order) is built through the library's chapter/sub-level API, never
     * hand-written XML.
     */
    public function export(Project $project): string
    {
        $tree = $this->filteredTree($project);

        // The one user-input failure of the whole pipeline: nothing survived the
        // skip-empty filter, so there is no content to package. Thrown BEFORE any temp file
        // exists; task 06's controller turns it into a redirect-back-with-error. Every other
        // failure below is a generator bug and throws loudly (see validatePackage()).
        if ($tree->isEmpty()) {
            throw EpubExportException::nothingToExport();
        }

        $book = new EPub(self::EPUB_VERSION, $this->language($project));

        $this->applyMetadata($book, $project);
        $this->applyCover($book, $project);
        $book->addCSSFile(self::STYLESHEET_FILENAME, 'epub-styles', $this->stylesheet());
        $this->addFrontMatter($book, $project, $tree);
        $this->addNavigation($book, $project, $tree);

        $path = $this->freshTempEpubPath();

        try {
            // getBook() finalizes the package (OPF, nav, NCX, zip) and returns the raw
            // bytes; writing them to our own uuid-named temp path keeps the same temp-file
            // lifecycle/cleanup contract as StaticSiteExporter rather than delegating the
            // filename to the library's saveBook().
            $bytes = $book->getBook();

            if (file_put_contents($path, $bytes) === false) {
                throw new RuntimeException("Unable to write the generated epub to {$path}.");
            }

            // Post-process the finalized OPF (dc:language + dc:source), THEN structurally
            // validate the shipped package. Validation runs last so it checks exactly what
            // the reader will open, including the corrections above.
            $this->normalizeOpf($path, $project);
            $this->validatePackage($path);
        } catch (\Throwable $e) {
            if (is_file($path)) {
                unlink($path);
            }

            throw $e;
        }

        return $path;
    }

    /**
     * The epub-only Markdown converter, built lazily and reused across every scene of a
     * single export. Kept private so the SmartPunct typography can never leak out of this
     * service into the app's shared Scene::renderedContents render path.
     */
    private ?CommonMarkConverter $converter = null;

    /**
     * Load the project's act → chapter → scene tree, position-ordered at every level
     * (mirrors StaticSiteExporter::loadBookTree()'s eager-load shape), then apply the
     * export-time skip-empty filter:
     *   - drop any Chapter with zero Scenes, then
     *   - drop any Act left with zero surviving Chapters.
     *
     * The result is the exact tree the next tasks (04 nav/spine, 05 validation) walk, and
     * the tree the tests assert against directly. An empty Collection here means "nothing
     * to export" — task 05 turns that into EpubExportException.
     *
     * @return Collection<int, Act>
     */
    public function filteredTree(Project $project): Collection
    {
        $acts = $project->acts()
            ->with([
                'chapters' => fn ($query) => $query->orderBy('position'),
                'chapters.scenes' => fn ($query) => $query->orderBy('position'),
            ])
            ->orderBy('position')
            ->get();

        return $acts
            ->each(function (Act $act) {
                // Keep only chapters that actually have scenes; re-index so callers can
                // rely on a clean 0..n list rather than the pre-filter gaps.
                $act->setRelation(
                    'chapters',
                    $act->chapters->filter(
                        fn (Chapter $chapter) => $chapter->scenes->isNotEmpty()
                    )->values()
                );
            })
            ->filter(fn (Act $act) => $act->chapters->isNotEmpty())
            ->values();
    }

    /**
     * Render one Act divider document as an XHTML string: "Act {position}" plus the Act's
     * name on its own line (omitted when blank). The Act `description` is never rendered.
     */
    public function renderAct(Act $act, Project $project): string
    {
        return view('exports.epub.act', [
            'position' => $act->position,
            'name' => $act->name,
            'language' => $this->language($project),
        ])->render();
    }

    /**
     * Render one Chapter document as an XHTML string: "Chapter {position}: {name}"
     * followed by its Scenes' Markdown compiled to HTML and <hr/>-joined (no per-scene
     * titles, no Chapter `description`). Scenes must already be position-ordered — pass a
     * Chapter taken from {@see filteredTree()}, whose scenes are ordered and non-empty.
     */
    public function renderChapter(Chapter $chapter, Project $project): string
    {
        $renderedScenes = $chapter->scenes
            ->map(fn (Scene $scene): string => $this->renderSceneContents($scene))
            ->all();

        return view('exports.epub.chapter', [
            'position' => $chapter->position,
            'name' => $chapter->name,
            'renderedScenes' => $renderedScenes,
            'language' => $this->language($project),
        ])->render();
    }

    /**
     * Render the story title page: the Project's name, centered and in larger text, as its
     * own page — the first content document in the book, before the table of contents.
     */
    public function renderTitlePage(Project $project): string
    {
        return view('exports.epub.title', [
            'name' => $project->name,
            'language' => $this->language($project),
        ])->render();
    }

    /**
     * Render the in-book table of contents: a nested list of every surviving Act (its nav
     * title) with its surviving Chapters underneath, each linking to the page
     * {@see addNavigation()} will give it. Distinct from the EPUB 3 nav document the
     * library builds automatically (that's reader chrome); this is a real page in the
     * spine, placed right after the title page.
     *
     * @param  Collection<int, Act>  $tree
     */
    public function renderToc(Project $project, Collection $tree): string
    {
        $entries = $tree->map(fn (Act $act) => [
            'href' => $this->actFileName($act),
            'label' => $this->actNavTitle($act),
            'chapters' => $act->chapters->map(fn (Chapter $chapter) => [
                'href' => $this->chapterFileName($chapter),
                'label' => $this->chapterNavTitle($chapter),
            ])->all(),
        ])->all();

        return view('exports.epub.toc', [
            'entries' => $entries,
            'language' => $this->language($project),
        ])->render();
    }

    /**
     * The epub stylesheet's contents, read from the single source file. Task 04 writes
     * this into the package under {@see STYLESHEET_FILENAME}.
     */
    public function stylesheet(): string
    {
        return file_get_contents(resource_path('views/exports/epub/styles.css'));
    }

    /**
     * Map the Project's fields onto the library's metadata setters. Optional fields
     * (author, publisher, rights, ISBN) are only set when present, so the OPF never carries
     * empty/placeholder values for fields the author left blank.
     *
     * Identifiers: the generated `urn:imagoldfish:project:{id}` is always the package's
     * unique identifier (a URI-scheme dc:identifier). When the Project has an ISBN, a
     * SECOND dc:identifier is added (expressed as `urn:isbn:{isbn}`) — it never replaces the
     * generated URN.
     */
    private function applyMetadata(EPub $book, Project $project): void
    {
        $book->setTitle($project->name);
        $book->setLanguage($this->language($project));
        $book->setIdentifier($this->primaryIdentifier($project), EPub::IDENTIFIER_URI);

        if (filled($project->author)) {
            // The sort key doubles as the display name — the app has no separate "sort as"
            // field, so there is nothing better to supply.
            $book->setAuthor($project->author, $project->author);
        }

        if (filled($project->publisher)) {
            // The publisher URL is intentionally empty: the app stores only a name, and a
            // blank URL keeps the library from emitting a spurious dc:relation.
            $book->setPublisher($project->publisher, '');
        }

        if (filled($project->rights)) {
            $book->setRights($project->rights);
        }

        if (filled($project->isbn)) {
            // EPUB 3 does not render the legacy `opf:scheme` attribute, so the ISBN scheme
            // is expressed via the standard `urn:isbn:` URI form as a second dc:identifier,
            // added through the library's custom-metadata API rather than hand-written XML.
            $book->addCustomMetaValue(new DublinCore(DublinCore::IDENTIFIER, 'urn:isbn:'.$project->isbn));
        }

        // Accessibility metadata is always present (built via the library's native methods,
        // never hand-written OPF XML) — this is a text-only publication navigable by its TOC.
        $book->setAccessibilitySummary(self::ACCESSIBILITY_SUMMARY);
        $book->addAccessMode(self::ACCESS_MODE);
        $book->addAccessibilityFeature(self::ACCESSIBILITY_FEATURE);
    }

    /**
     * Embed the Project's cover image when set. The bytes are read straight off the
     * `public` disk (never via the /storage URL, so the export never depends on
     * `php artisan storage:link`) and handed to the library's cover API, which builds the
     * cover manifest item, the OPF `<meta name="cover">`, and the cover page. A cover row
     * that points at a missing file is silently skipped rather than aborting the export.
     */
    private function applyCover(EPub $book, Project $project): void
    {
        if (blank($project->cover_image)) {
            return;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($project->cover_image)) {
            return;
        }

        $bytes = $disk->get($project->cover_image);
        if ($bytes === null) {
            return;
        }

        // basename gives the library a clean filename (for the manifest item + extension);
        // the mime type comes from the stored file, falling back to extension-derivation
        // inside the library when the disk can't report one.
        $book->setCoverImage(
            basename($project->cover_image),
            $bytes,
            $disk->mimeType($project->cover_image) ?: null
        );
    }

    /**
     * Add the two front-matter pages, in reading order, before any Act/Chapter content:
     * the story title page, then the in-book table of contents (Acts with their surviving
     * Chapters nested underneath — the same shape {@see addNavigation()} wires into the
     * EPUB 3 nav). Both are added at nav root level, alongside (not nested under) the Acts
     * that follow, so they read as their own front-matter entries rather than pretending to
     * be part of the story tree.
     *
     * @param  Collection<int, Act>  $tree
     */
    private function addFrontMatter(EPub $book, Project $project, Collection $tree): void
    {
        $titleXhtml = $this->renderTitlePage($project);
        $this->assertXmlWellFormed($titleXhtml, self::TITLE_FILE);
        $book->addChapter($project->name, self::TITLE_FILE, $titleXhtml);

        $tocXhtml = $this->renderToc($project, $tree);
        $this->assertXmlWellFormed($tocXhtml, self::TOC_FILE);
        $book->addChapter('Table of Contents', self::TOC_FILE, $tocXhtml);
    }

    /**
     * Add every surviving Act divider page and Chapter page as an EPUB chapter, wiring the
     * two-level nav as it goes: each Act is a root-level nav entry, and its surviving
     * Chapters are nested one level below it (subLevel/backLevel bracket the Act's
     * children). Both levels are walked in `position` order because {@see filteredTree()}
     * already returns them ordered.
     *
     * @param  Collection<int, Act>  $tree
     */
    private function addNavigation(EPub $book, Project $project, Collection $tree): void
    {
        foreach ($tree as $act) {
            $actFile = $this->actFileName($act);
            $actXhtml = $this->renderAct($act, $project);
            // Well-formedness gate BEFORE the document is buried in the package, so a
            // malformed page (a generator bug) is caught and named at its source.
            $this->assertXmlWellFormed($actXhtml, $actFile);
            $book->addChapter($this->actNavTitle($act), $actFile, $actXhtml);

            // Descend into the Act's nav entry so its Chapters nest underneath it.
            $book->subLevel();

            foreach ($act->chapters as $chapter) {
                $chapterFile = $this->chapterFileName($chapter);
                $chapterXhtml = $this->renderChapter($chapter, $project);
                $this->assertXmlWellFormed($chapterXhtml, $chapterFile);
                $book->addChapter($this->chapterNavTitle($chapter), $chapterFile, $chapterXhtml);
            }

            // Back to the root level before the next Act.
            $book->backLevel();
        }
    }

    /**
     * Rewrite two OPF metadata values the library gets wrong, in a single reopen of the
     * finalized package:
     *
     *  - `dc:language` — rampmaster/phpepub's `setLanguage()` silently rejects any code that
     *    is not exactly two characters (its guard is `mb_strlen($language) != 2`), so
     *    region-tagged codes like `fr-CA`/`en-US` fall back to the library default `en` in
     *    the OPF (the content documents already carry the correct code — this service renders
     *    their `lang` attributes itself). Idempotent for plain two-letter codes.
     *
     *  - `dc:source` — the library derives this from the request environment during
     *    finalize() (`getCurrentServerURL()`), which yields the real server URL under an HTTP
     *    request but the malformed `http://:/` under CLI/queue. Worse, its `setSourceURL()`
     *    API is unusable here: finalize() unconditionally overwrites `sourceURL` whenever the
     *    publisher URL is empty (a library bug — it tests `publisherURL` but assigns
     *    `sourceURL`), and this service always passes an empty publisher URL. So the only way
     *    to get a deterministic, meaningful value is to rewrite the finalized OPF. It is
     *    normalized to the app URL so an export is byte-identical regardless of whether it
     *    ran under HTTP or the CLI. The value stays schema-valid (`dc:source` is free text).
     */
    private function normalizeOpf(string $epubPath, Project $project): void
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
                '<dc:language>'.$this->escapeXmlText($this->language($project)).'</dc:language>',
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
     * Structurally validate the finalized package before it is returned. Two PHP-native
     * checks, no JVM/epubcheck dependency (the export page links authors to the real
     * epubcheck for full conformance). A failure here means the generator produced
     * non-conformant output — a server-side BUG — so this throws loudly rather than
     * degrading; it never throws {@see EpubExportException} (that is reserved for the
     * user-facing empty-project case).
     *
     *  1. Well-formedness — every shipped `.xhtml` document (our Act/Chapter pages AND the
     *     library-generated nav / cover page) must parse with {@see DOMDocument::loadXML()}.
     *  2. Schema — the OPF package document must validate against the vendored EPUB 3
     *     RelaxNG grammar. (The nav's official grammar is the entire XHTML5+MathML3+SVG
     *     RelaxNG tree, which libxml cannot process, so the nav is covered by the
     *     well-formedness check only — see resources/epub-schemas/README.md.)
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

    /**
     * Assert an XML string is well-formed, capturing libxml's own errors (never suppressing
     * them). Throws with the offending document's name and the parser diagnostics so a
     * generator regression is immediately traceable.
     */
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

    /**
     * Assert the OPF validates against the vendored EPUB 3 RelaxNG schema, capturing libxml's
     * validation errors. Throws (a generator bug) on any schema violation.
     */
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

    /**
     * Flatten libxml error structs into a single readable diagnostic line.
     *
     * @param  array<int, \LibXMLError>  $errors
     */
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

    /**
     * Escape a text value for safe insertion between XML element tags.
     */
    private function escapeXmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }

    /**
     * The nav label for an Act: "Act {position}", with ": {name}" appended when the Act has
     * a name (mirroring the page heading + name shape).
     */
    private function actNavTitle(Act $act): string
    {
        return filled($act->name)
            ? "Act {$act->position}: {$act->name}"
            : "Act {$act->position}";
    }

    /**
     * The nav label for a Chapter: "Chapter {position}: {name}" (matching the page heading).
     */
    private function chapterNavTitle(Chapter $chapter): string
    {
        return "Chapter {$chapter->position}: {$chapter->name}";
    }

    /**
     * The in-package filename for an Act's page. Keyed off the stable database id (not
     * position) so two same-positioned entries in different parents can never collide.
     * Single source of truth shared by {@see addNavigation()} and {@see renderToc()} so
     * the TOC page's links can never drift from the files actually packaged.
     */
    private function actFileName(Act $act): string
    {
        return "act-{$act->id}.xhtml";
    }

    /**
     * The in-package filename for a Chapter's page. See {@see actFileName()}.
     */
    private function chapterFileName(Chapter $chapter): string
    {
        return "chapter-{$chapter->id}.xhtml";
    }

    /**
     * The always-present primary identifier: a stable URN derived from the project id.
     */
    private function primaryIdentifier(Project $project): string
    {
        return "urn:imagoldfish:project:{$project->id}";
    }

    /**
     * A fresh, collision-free temp path for the generated epub under storage/app/exports
     * (created on demand) — the same lifecycle as {@see StaticSiteExporter}'s temp zips, so
     * concurrent exports never clobber one another and the controller can delete-after-send.
     */
    private function freshTempEpubPath(): string
    {
        $directory = storage_path('app/exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.DIRECTORY_SEPARATOR.Str::uuid().'.epub';
    }

    /**
     * Compile a scene's Markdown `contents` to HTML through the epub-only SmartPunct
     * converter. Null contents render to an empty string, mirroring the null-guard in
     * Scene::renderedContents — WITHOUT going through that accessor, so the app's shared
     * render path stays untouched.
     */
    private function renderSceneContents(Scene $scene): string
    {
        return (string) $this->converter()->convert($scene->contents ?? '');
    }

    /**
     * The lazily-built, SmartPunct-configured CommonMark converter. Reused within one
     * export so a chapter's scenes share a single converter instance.
     */
    private function converter(): CommonMarkConverter
    {
        if ($this->converter === null) {
            $converter = new CommonMarkConverter;
            // SmartPunctExtension does dashes, ellipses, and smart quotes together (the
            // grilled decision). It lives ONLY on this instance — never on Str::markdown
            // or Scene::renderedContents.
            $converter->getEnvironment()->addExtension(new SmartPunctExtension);

            $this->converter = $converter;
        }

        return $this->converter;
    }

    /**
     * The Project's BCP-47 language code, falling back to 'en' if it is somehow empty. Drives
     * every content document's `lang`/`xml:lang`. `language` is a BookLanguage enum (a closed
     * dropdown, not free text) as of the language-selector change; this unwraps it to the
     * plain code the epub-building APIs expect.
     */
    private function language(Project $project): string
    {
        return $project->language?->value ?? self::DEFAULT_LANGUAGE;
    }
}
