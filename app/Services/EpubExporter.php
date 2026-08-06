<?php

namespace App\Services;

use App\Enums\ChapterTitleFormat;
use App\Enums\TableOfContentsDepth;
use App\Exceptions\EpubExportException;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Project;
use App\Models\PublicationSetting;
use App\Models\Scene;
use App\Support\RichText;
use App\Support\StoryNumbering;
use DOMDocument;
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
 * Builds the in-memory content of a project's EPUB export.
 *
 * HTTP-agnostic and analogous to {@see StaticSiteExporter}: it takes a Project and
 * produces the ordered act/chapter tree and the rendered XHTML content documents.
 * {@see export()} packages them into an .epub file on disk.
 *
 * The rampmaster/phpepub library owns the mimetype, container.xml, the OPF
 * (metadata/manifest/spine) and the EPUB 3 nav document. This service owns the XHTML
 * content (Blade), the metadata values it gives the library, the CSS, and the
 * Act → Chapter (→ Scene) navigation.
 *
 * ## Page order and navigation depth
 *
 * {@see addSections()} follows `PublicationSetting::section_order`. The author sets the
 * position of the title page, the table of contents, the body, and the Markdown pages
 * (dedication, acknowledgements, preface, postface). That in-book table of contents is a
 * real spine page, not the reader-chrome nav document the library builds.
 *
 * `table_of_contents_depth` sets the navigation depth:
 *   - "Acts" folds each act's chapters into one combined spine page, so only acts appear
 *     in the nav.
 *   - "Chapters" (the default) is the two-level Act → Chapter tree.
 *   - "Scenes" adds a third level of per-scene in-page anchor links.
 *
 * {@see addChapterCoverPage()} puts a full-page cover before a chapter's content, gated by
 * `include_chapter_covers`. The cover page is a nav SIBLING of the chapter, never a child,
 * so it does not disturb the nested scene anchors at "Scenes" depth.
 *
 * {@see addAppendixSection()} fills the reserved `appendix` slot: a heading page plus one
 * page per selected codex entry (name + rich-HTML description normalised to XHTML). Its
 * gates are `include_codex_appendix` and `appendix_entry_types`.
 *
 * ## The two kinds of failure
 *
 * A project with no scenes throws {@see EpubExportException}. That is a USER problem, and
 * the controller surfaces it as a validation-style error.
 *
 * {@see validatePackage()} checks every shipped XHTML document for XML well-formedness and
 * validates the OPF against the vendored EPUB 3 RelaxNG grammar. A failure there is a
 * generator BUG, so it throws a plain RuntimeException — let it 500 and be logged.
 *
 * > [!WARNING]
 * > Two isolation rules. Break either one and the damage is silent.
 * > - Scene contents render through this service's OWN CommonMark converter, configured
 * >   with SmartPunctExtension (smart dashes, ellipses and quotes). Never through
 * >   `Scene::renderedContents` — that accessor is the shared render path for the Story
 * >   overview, the share page and the book/ export, and must stay byte-for-byte equal.
 * > - HTML renders through the Blade views under resources/views/exports/epub/, never from
 * >   strings built here. The book/ layer follows the same rule.
 */
class EpubExporter
{
    public function __construct(private CoverImageService $coverImageService) {}

    /**
     * The stylesheet's flat filename inside the epub package. The CSS is written beside
     * the content documents under this name, and the Blade layout links to it by exactly
     * this name — single source of truth, so the link and the packaged file can never
     * drift apart.
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
     * `resource_path()`. {@see validatePackage()} validates the generated OPF against
     * this. Its two
     * `<include>`d modules (`datatypes.rng`, `epub-prefix-attr.rng`) live beside it and are
     * resolved relative to this file — see resources/epub-schemas/README.md for provenance.
     */
    private const OPF_SCHEMA = 'epub-schemas/package-30.rng';

    /**
     * Filenames for the title page and the in-book table of contents, added by
     * {@see addTitleSection()} / {@see addTocSection()} wherever `section_order` places
     * them (title is pinned first). Distinct from the EPUB 3 nav document (the reader-chrome
     * TOC rampmaster/phpepub builds automatically) — these are real, readable content pages
     * in the spine.
     */
    private const TITLE_FILE = 'title.xhtml';

    private const TOC_FILE = 'toc.xhtml';

    /**
     * Filename and heading for the codex appendix section heading/cover page, added by
     * {@see addAppendixSection()} at the `appendix` slot of the `section_order` walk.
     * The individual entry pages ({@see appendixEntryFileName()}) nest one nav level beneath it.
     */
    private const APPENDIX_FILE = 'appendix.xhtml';

    private const APPENDIX_HEADING = 'Appendix';

    /**
     * Front/back-matter section keys {@see addSections()} renders as a Markdown page: each
     * maps its `section_order` key to the Project Markdown column it reads, the
     * `PublicationSetting` toggle that gates it, the page heading, and its in-package
     * filename. Single source of truth for {@see addMatterSection()}, so the four sections
     * share one code path instead of four near-identical private methods — the "no magic
     * strings" invariant, applied to the matter-section wiring itself.
     *
     * `title`, `toc`, `body`, and `appendix` are NOT matter sections: they render through
     * their own dedicated methods ({@see addTitleSection()}, {@see addTocSection()},
     * {@see addBody()}, {@see addAppendixSection()}).
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
     * Package a Project's rendered tree into an actual .epub file and return the path to it
     * on disk. The calling controller streams the file and deletes it after
     * send; the temp file is also removed here if packaging throws, so a failed export never
     * leaks a partial file — mirroring {@see StaticSiteExporter::export()}.
     *
     * The two-level TOC/nav (Acts as parent entries, their Chapters nested underneath, both
     * in `position` order) is built through the library's chapter/sub-level API, never
     * hand-written XML.
     */
    public function export(Project $project): string
    {
        $tree = $this->bookTree($project);

        // The one user-input failure of the whole pipeline: the project has not a single
        // scene anywhere, so the book would be nothing but blank outline pages. Thrown
        // BEFORE any temp file exists; the controller turns it into a
        // redirect-back-with-error. Every other failure below is a generator bug and throws
        // loudly (see validatePackage()).
        if (! $this->hasAnyScene($tree)) {
            throw EpubExportException::nothingToExport();
        }

        // Read once and thread through every private method that needs it. Lazy: a
        // project with no saved row gets an unsaved default instance whose toggles
        // reproduce today's output
        // byte-for-byte (see PublicationSettingTest / the defaults-regression guard in
        // EpubExporterTest).
        $settings = $project->publicationSettingOrDefault();

        // Built once from the already-loaded $tree (fromActs() fires zero queries) and
        // threaded through every render/nav-label call below, so every act/chapter number
        // in the package is derived from THIS SAME tree — see StoryNumbering.
        $numbering = StoryNumbering::fromActs($tree);

        $book = new EPub(self::EPUB_VERSION, $this->language($project));

        $this->applyMetadata($book, $project, $settings);
        $this->applyCover($book, $project, $settings);
        $book->addCSSFile(self::STYLESHEET_FILENAME, 'epub-styles', $this->stylesheet());
        $this->addSections($book, $project, $tree, $settings, $numbering);

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
     * Load the project's WHOLE act → chapter → scene tree, position-ordered at every level
     * (mirrors StaticSiteExporter::loadBookTree()'s eager-load shape). Nothing is dropped:
     * a Chapter with zero Scenes exports as a heading-only page, and an Act with zero
     * Chapters keeps its divider.
     *
     * That is a deliberate reversal of the old skip-empty filter. Authors organise before
     * they write, so an empty chapter is a placeholder, not an accident — filtering it out
     * shifted every later chapter's number the moment the author started writing it, and the
     * exported book disagreed with the app in the meantime. Exporting the full outline keeps
     * export numbers equal to app numbers at all times, and matches what
     * {@see StaticSiteExporter} has always published.
     *
     * The result is the exact tree the nav/spine and validation steps walk, and the tree the
     * tests assert against directly. "Nothing to export" is no longer an empty tree — it is
     * {@see hasAnyScene()} returning false, which {@see export()} turns into an
     * EpubExportException.
     *
     * @return Collection<int, Act>
     */
    public function bookTree(Project $project): Collection
    {
        return $project->acts()
            ->with([
                'chapters' => fn ($query) => $query->orderBy('position'),
                'chapters.scenes' => fn ($query) => $query->orderBy('position'),
            ])
            ->orderBy('position')
            ->get();
    }

    /**
     * Does the exported tree contain a single scene anywhere? The one thing that makes an
     * export worth producing now that empty chapters and acts are kept: without it a brand
     * new outline would export as a book of blank pages.
     *
     * Reads the already eager-loaded relations rather than issuing a count query, so the
     * guard costs nothing beyond {@see bookTree()}'s own three queries.
     *
     * @param  Collection<int, Act>  $tree
     */
    private function hasAnyScene(Collection $tree): bool
    {
        return $tree->contains(
            fn (Act $act) => $act->chapters->contains(
                fn (Chapter $chapter) => $chapter->scenes->isNotEmpty()
            )
        );
    }

    /**
     * Render one Act divider document as an XHTML string: "Act {position}" plus the Act's
     * name on its own line (omitted when blank). The Act `description` (rich HTML,
     * normalised to well-formed XHTML) is rendered underneath only when
     * `include_act_descriptions` is on and the description is non-empty.
     *
     * $settings is nullable so callers/tests can render with the project's lazy default;
     * the exporter's own pipeline always threads the resolved setting through. $numbering
     * is nullable the same way — a caller without a pre-built one (tests calling this
     * directly) gets it derived fresh via {@see StoryNumbering::forProject()}.
     */
    public function renderAct(Act $act, Project $project, ?PublicationSetting $settings = null, ?StoryNumbering $numbering = null): string
    {
        $settings ??= $project->publicationSettingOrDefault();
        $numbering ??= StoryNumbering::forProject($project);

        return view('exports.epub.act', $this->actViewData($act, $project, $settings, $numbering))->render();
    }

    /**
     * Render one Act together with every one of its Chapters as a single spine document —
     * the page used at the "Acts" {@see TableOfContentsDepth}. The navigation lists only
     * acts at that depth, so the chapters cannot each own a nav entry; folding them into the
     * act's own page keeps all prose in the reading order behind a single act nav entry.
     * See exports/epub/act-combined.blade.php for why the library forces this shape.
     *
     * $settings and $numbering are nullable so callers/tests can render with the project's
     * lazy default and a freshly-derived numbering, same as {@see renderAct()}.
     */
    public function renderActWithChapters(Act $act, Project $project, ?PublicationSetting $settings = null, ?StoryNumbering $numbering = null): string
    {
        $settings ??= $project->publicationSettingOrDefault();
        $numbering ??= StoryNumbering::forProject($project);

        return view('exports.epub.act-combined', array_merge(
            $this->actViewData($act, $project, $settings, $numbering),
            [
                'chapters' => $act->chapters
                    ->map(fn (Chapter $chapter) => $this->chapterViewData($chapter, $project, $settings, $numbering))
                    ->all(),
            ]
        ))->render();
    }

    /**
     * The view data for an Act divider body — shared by the standalone act page
     * ({@see renderAct()}) and the combined act page ({@see renderActWithChapters()}) so the
     * two rendering paths can never drift.
     *
     * `number` is the project-wide, gap-free rank from {@see StoryNumbering} — NOT
     * `$act->position`, which is the per-parent, gappy sort key. See StoryNumbering's own
     * docblock for why the export must never display `position`.
     *
     * @return array<string, mixed>
     */
    private function actViewData(Act $act, Project $project, PublicationSetting $settings, StoryNumbering $numbering): array
    {
        return [
            'number' => $numbering->act($act),
            'name' => $act->name,
            'showDescription' => $settings->include_act_descriptions,
            'description' => RichText::toXhtmlFragment($act->description),
            'language' => $this->language($project),
        ];
    }

    /**
     * Render one Chapter document as an XHTML string. The heading comes from
     * {@see ChapterTitleFormat::format()} (the single source of truth shared with the
     * nav/TOC label). Its Scenes' Markdown is compiled to HTML and interleaved with the
     * configured {@see DividerType} snippet. Optional, config-gated additions — each
     * rendered only when its toggle is on AND the underlying content is non-empty:
     *   - the Chapter `description` (rich HTML → XHTML) under the heading,
     *   - a per-scene title (`Scene.name`) above each scene, and
     *   - a per-scene `description` (rich HTML → XHTML) above each scene body.
     *
     * Scenes must already be position-ordered — pass a Chapter taken from
     * {@see bookTree()}. A Chapter with no scenes is legitimate and renders as a
     * heading-only page (no app-written filler). $settings and $numbering are nullable so
     * callers/tests can render with the project's lazy default and a freshly-derived
     * numbering, same as {@see renderAct()}.
     */
    public function renderChapter(Chapter $chapter, Project $project, ?PublicationSetting $settings = null, ?StoryNumbering $numbering = null): string
    {
        $settings ??= $project->publicationSettingOrDefault();
        $numbering ??= StoryNumbering::forProject($project);

        return view('exports.epub.chapter', $this->chapterViewData($chapter, $project, $settings, $numbering))->render();
    }

    /**
     * The view data for a Chapter body — shared by the standalone chapter page
     * ({@see renderChapter()}) and the combined act page ({@see renderActWithChapters()}).
     *
     * The `sceneAnchors` flag turns on per-scene `id="scene-{id}"` anchors, but ONLY at the
     * "Scenes" {@see TableOfContentsDepth}: that is the sole depth whose nav/TOC links to
     * `chapter-{id}.xhtml#scene-{id}`, so the anchors exist exactly when something points at
     * them and the default "Chapters" depth renders byte-for-byte as before. Each scene
     * carries its stable id for that anchor.
     *
     * `number` — and the number fed into `heading` — is the project-wide, gap-free rank
     * from {@see StoryNumbering}, NOT `$chapter->position`. See {@see actViewData()}.
     *
     * @return array<string, mixed>
     */
    private function chapterViewData(Chapter $chapter, Project $project, PublicationSetting $settings, StoryNumbering $numbering): array
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
            'language' => $this->language($project),
        ];
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
     * Render the in-book table of contents: a nested list of every Act (its nav title) with
     * its Chapters underneath, each linking to the page
     * {@see addBody()} will give it. Distinct from the EPUB 3 nav document the
     * library builds automatically (that's reader chrome); this is a real page in the
     * spine, at whatever position `section_order` places it.
     *
     * @param  Collection<int, Act>  $tree
     */
    public function renderToc(Project $project, Collection $tree, ?PublicationSetting $settings = null, ?StoryNumbering $numbering = null): string
    {
        $settings ??= $project->publicationSettingOrDefault();
        // fromActs(), not forProject(): $tree is already the whole loaded tree, so deriving
        // from it avoids a second set of queries a caller-supplied $numbering would have
        // avoided anyway.
        $numbering ??= StoryNumbering::fromActs($tree);
        $format = $settings->chapter_title_format;
        $depth = $settings->table_of_contents_depth;

        // The depth toggle decides how far each entry's children array is populated; the
        // toc view has no depth logic of its own — it nests only while a children array is
        // non-empty. "Acts" ⇒ no chapters; "Chapters" ⇒ chapters, no scenes; "Scenes" ⇒
        // chapters with per-scene anchor links.
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
            'language' => $this->language($project),
        ])->render();
    }

    /**
     * The epub stylesheet's contents, read from the single source file. It is written
     * into the package under {@see STYLESHEET_FILENAME}.
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
     *
     * Each optional block is additionally gated by its `PublicationSetting` toggle:
     * `include_author`, `include_publisher`, `include_rights`, `include_isbn`. All four
     * default `true`, so a default or absent setting emits the full metadata. Title,
     * language, the primary URN and the accessibility metadata are never gated, by design.
     */
    private function applyMetadata(EPub $book, Project $project, PublicationSetting $settings): void
    {
        $book->setTitle($project->name);
        $book->setLanguage($this->language($project));
        $book->setIdentifier($this->primaryIdentifier($project), EPub::IDENTIFIER_URI);

        if ($settings->include_author && filled($project->author)) {
            // The sort key doubles as the display name — the app has no separate "sort as"
            // field, so there is nothing better to supply.
            $book->setAuthor($project->author, $project->author);
        }

        if ($settings->include_publisher && filled($project->publisher)) {
            // The publisher URL is intentionally empty: the app stores only a name, and a
            // blank URL keeps the library from emitting a spurious dc:relation.
            $book->setPublisher($project->publisher, '');
        }

        if ($settings->include_rights && filled($project->rights)) {
            $book->setRights($project->rights);
        }

        if ($settings->include_isbn && filled($project->isbn)) {
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
     *
     * Gated by `$settings->include_project_cover`, default `true` — a default or absent
     * setting embeds the cover.
     */
    private function applyCover(EPub $book, Project $project, PublicationSetting $settings): void
    {
        if (! $settings->include_project_cover || blank($project->cover_image)) {
            return;
        }

        $bytes = $this->coverImageService->bytes($project->cover_image);
        if ($bytes === null) {
            return;
        }

        // basename gives the library a clean filename (for the manifest item + extension);
        // the mime type comes from the stored file, falling back to extension-derivation
        // inside the library when the disk can't report one.
        $book->setCoverImage(
            basename($project->cover_image),
            $bytes,
            $this->coverImageService->mimeType($project->cover_image) ?: null
        );
    }

    /**
     * Drive the whole book's page order off `section_order`. Falls back to
     * {@see PublicationSetting::SECTION_KEYS} (the standard reading order) when the setting
     * carries no order — the lazy-default row from `publicationSettingOrDefault()` already
     * fills this in, so that fallback is a belt-and-braces guard, not the common path.
     *
     * `title` is always first in practice (the model pins it there and the config form
     * cannot reorder it — see `PublicationSetting::PINNED_FIRST_SECTION`), but this method
     * does not special-case that: it simply renders whatever order it is given, one section
     * at a time, so the pinning is enforced in exactly one place (the model).
     *
     * Each front/back-matter Markdown section (`dedication`/`acknowledgements`/`preface`/
     * `postface`) renders only when its `include_*` toggle is on AND the Project's field is
     * non-empty — see {@see addMatterSection()}. The `appendix` slot renders the codex
     * appendix via {@see addAppendixSection()}.
     *
     * @param  Collection<int, Act>  $tree
     */
    private function addSections(EPub $book, Project $project, Collection $tree, PublicationSetting $settings, StoryNumbering $numbering): void
    {
        $order = $settings->section_order ?? PublicationSetting::SECTION_KEYS;

        foreach ($order as $section) {
            match ($section) {
                'title' => $this->addTitleSection($book, $project),
                'dedication', 'acknowledgements', 'preface', 'postface' => $this->addMatterSection($book, $project, $settings, $section),
                'toc' => $this->addTocSection($book, $project, $tree, $settings, $numbering),
                'body' => $this->addBody($book, $project, $tree, $settings, $numbering),
                'appendix' => $this->addAppendixSection($book, $project, $settings),
                // Any unrecognised key renders nothing.
                default => null,
            };
        }
    }

    /**
     * Add the story title page as its own root-level nav entry.
     */
    private function addTitleSection(EPub $book, Project $project): void
    {
        $titleXhtml = $this->renderTitlePage($project);
        $this->assertXmlWellFormed($titleXhtml, self::TITLE_FILE);
        $book->addChapter($project->name, self::TITLE_FILE, $titleXhtml);
    }

    /**
     * Add the in-book table of contents page as its own root-level nav entry (Acts with
     * their Chapters nested underneath — the same shape {@see addBody()} wires into the
     * EPUB 3 nav).
     *
     * @param  Collection<int, Act>  $tree
     */
    private function addTocSection(EPub $book, Project $project, Collection $tree, PublicationSetting $settings, StoryNumbering $numbering): void
    {
        $tocXhtml = $this->renderToc($project, $tree, $settings, $numbering);
        $this->assertXmlWellFormed($tocXhtml, self::TOC_FILE);
        $book->addChapter('Table of Contents', self::TOC_FILE, $tocXhtml);
    }

    /**
     * Add one front/back-matter Markdown page (dedication/acknowledgements/preface/postface)
     * as its own root-level nav entry, gated by BOTH its `include_*` toggle AND non-empty
     * content — a disabled toggle or an empty field renders nothing at all. Unknown keys
     * (defensive only — {@see MATTER_SECTIONS} is the closed set) also render nothing.
     */
    private function addMatterSection(EPub $book, Project $project, PublicationSetting $settings, string $key): void
    {
        $config = self::MATTER_SECTIONS[$key] ?? null;
        if ($config === null) {
            return;
        }

        if (! $settings->{$config['toggle']}) {
            return;
        }

        $markdown = $project->{$config['field']};
        if (blank($markdown)) {
            return;
        }

        $xhtml = $this->renderMatterPage($config['heading'], $markdown, $project);
        $this->assertXmlWellFormed($xhtml, $config['file']);
        $book->addChapter($config['heading'], $config['file'], $xhtml);
    }

    /**
     * Render one front/back-matter page: a heading plus the given Markdown compiled through
     * this service's own private SmartPunct converter — the SAME converter scene bodies use,
     * never {@see Scene::renderedContents} and never the rich-HTML sanitizer
     * (these Project columns are Markdown, like Scene.contents, not rich HTML like
     * descriptions/codex entries).
     */
    public function renderMatterPage(string $heading, string $markdown, Project $project): string
    {
        return view('exports.epub.matter', [
            'heading' => $heading,
            'body' => (string) $this->converter()->convert($markdown),
            'language' => $this->language($project),
        ])->render();
    }

    /**
     * Render the codex appendix section heading/cover page: a single "Appendix" heading, the
     * root nav entry the individual entry pages nest beneath ({@see addAppendixSection()}).
     */
    public function renderAppendixHeading(Project $project): string
    {
        return view('exports.epub.appendix', [
            'heading' => self::APPENDIX_HEADING,
            'language' => $this->language($project),
        ])->render();
    }

    /**
     * Render one codex appendix entry page: the entry's name as a heading plus its stored
     * rich-HTML `description` normalised to well-formed XHTML via {@see RichText::toXhtmlFragment()}
     * (the codex description is sanitized rich HTML, NOT Markdown — embedding it raw would break
     * {@see validatePackage()}). When `appendix_include_images` is on, the entry's FIRST media
     * image is embedded above the description; `$imagePath` is its in-book path
     * (already packaged by {@see addAppendixEntryImage()}), or null when there is no image or the
     * backing file was missing off disk — in which case the page renders text only.
     */
    public function renderAppendixEntry(CodexEntry $entry, Project $project, ?string $imagePath = null): string
    {
        return view('exports.epub.appendix-entry', [
            'name' => $entry->name,
            'imagePath' => $imagePath,
            'description' => RichText::toXhtmlFragment($entry->description),
            'language' => $this->language($project),
        ])->render();
    }

    /**
     * Add the codex appendix at the `appendix` slot of the `section_order` walk: a
     * heading/cover page as its own root-level nav entry, then one page per codex entry nested
     * one level beneath it — the same "section owns its children" nesting an Act uses for its
     * Chapters, and the right depth for the Act/Chapter/Scene nav structure.
     *
     * A true no-op — nothing added, no nav levels disturbed — unless ALL of:
     *   - `include_codex_appendix` is on (the default is off), AND
     *   - at least one `appendix_entry_types` is selected, AND
     *   - the project actually has codex entries of those types.
     * The last guard applies the general rule — a section renders only when it is enabled AND
     * has non-empty content — to the appendix. With the toggle on and types chosen but no
     * matching entries, a lone heading page would be pointless, so the whole section is
     * skipped.
     *
     * Entries are loaded filtered to the selected types and ordered by (`type`, `name`) — the
     * spec's ordering. When `appendix_include_images` is on, each entry's `media`
     * is eager-loaded and its FIRST image is embedded on the entry page via
     * {@see addAppendixEntryImage()}; the toggle stays off by default so no media is loaded.
     */
    private function addAppendixSection(EPub $book, Project $project, PublicationSetting $settings): void
    {
        if (! $settings->include_codex_appendix) {
            return;
        }

        $types = $settings->appendix_entry_types ?? [];
        if ($types === []) {
            return;
        }

        // The stored appendix_entry_types are CodexEntryType backing values; the `type` column
        // holds those same strings, so a plain whereIn filters to the selected types. Ordered by
        // (type, name) at the database — both are plain string columns.
        $query = $project->codexEntries()
            ->whereIn('type', $types)
            ->orderBy('type')
            ->orderBy('name');

        // Only pay for the entries' media when images are actually wanted. The
        // relation is ordered (collection, position) so `first image` resolution below is
        // deterministic and matches how the archive exporter orders the same rows. When
        // `appendix_include_images` is off, `media` is never loaded — no wasted query.
        if ($settings->appendix_include_images) {
            $query->with(['media' => fn ($relation) => $relation->orderBy('collection')->orderBy('position')]);
        }

        $entries = $query->get();

        if ($entries->isEmpty()) {
            return;
        }

        // The appendix heading/cover page — a root-level nav entry.
        $headingXhtml = $this->renderAppendixHeading($project);
        $this->assertXmlWellFormed($headingXhtml, self::APPENDIX_FILE);
        $book->addChapter(self::APPENDIX_HEADING, self::APPENDIX_FILE, $headingXhtml);

        // Each entry nests one nav level under the appendix heading.
        $book->subLevel();

        foreach ($entries as $entry) {
            // The first media image, packaged and its in-book path resolved — or null when
            // images are off, the entry has no image, or the file is missing off disk. Passed
            // into the view so the entry page renders with or without an illustration.
            $imagePath = $settings->appendix_include_images
                ? $this->addAppendixEntryImage($book, $entry)
                : null;

            $entryFile = $this->appendixEntryFileName($entry);
            $entryXhtml = $this->renderAppendixEntry($entry, $project, $imagePath);
            $this->assertXmlWellFormed($entryXhtml, $entryFile);
            $book->addChapter($entry->name, $entryFile, $entryXhtml);
        }

        $book->backLevel();
    }

    /**
     * Embed a codex entry's FIRST media image into the package and return its
     * in-book path for the entry page's `<img>`, or null when there is nothing to embed.
     *
     * "First image" is the first media row (in the eager-loaded (collection, position) order) that
     * both carries an `image/*` MIME type AND has bytes on disk — so a metadata-only imported row
     * (null path) or a non-image reference file (e.g. a PDF) is skipped over, not embedded. This is
     * a deliberate V1 scope limit: exactly one image per entry, never the whole gallery.
     *
     * A row whose file is missing off the `public` disk is skipped SILENTLY — the entry page still
     * renders (text only) and the export never fails, mirroring {@see applyCover()} /
     * {@see addChapterCoverPage()}'s missing-file behaviour. Bytes are read
     * through {@see CoverImageService::bytes()} (same `public` disk the codex media lives on), whose
     * null return is the missing-file signal.
     *
     * The image is added with the library's generic addFile() (manifest-only, no spine/nav entry)
     * and its path is namespaced by the entry's stable id so it can never collide with the project
     * cover, a chapter cover, or another entry's image.
     */
    private function addAppendixEntryImage(EPub $book, CodexEntry $entry): ?string
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

        $book->addFile($imagePath, 'appendix_image_'.$entry->id, $bytes, $mimeType);

        return $imagePath;
    }

    /**
     * Add every Act divider page and Chapter page as an EPUB chapter, wiring the two-level
     * nav as it goes: each Act is a root-level nav entry, and its Chapters are nested one
     * level below it (subLevel/backLevel bracket the Act's children). Both levels are walked
     * in `position` order because {@see bookTree()} already returns them ordered. This is the
     * `body` entry in `section_order` ({@see addSections()}).
     *
     * @param  Collection<int, Act>  $tree
     */
    private function addBody(EPub $book, Project $project, Collection $tree, PublicationSetting $settings, StoryNumbering $numbering): void
    {
        $format = $settings->chapter_title_format;
        $depth = $settings->table_of_contents_depth;

        foreach ($tree as $act) {
            $actFile = $this->actFileName($act);

            // "Acts" depth: one combined spine page per act (the act divider + all its
            // chapters), so the nav/TOC carries a single entry per act while every chapter's
            // prose stays in the reading order. The rampmaster/phpepub library couples spine
            // placement and nav entries in addChapter() (setNavHidden() is honoured by the
            // NCX but NOT the EPUB 3 nav — confirmed by spike), so a page-per-act is the only
            // way to keep chapters readable without giving each one its own nav entry. There
            // is no standalone "chapter's content page" at this depth, so each chapter's
            // cover page is added as a root-level nav entry immediately before the
            // combined act page holding that chapter's content — the closest this depth can
            // get to "immediately before that chapter's content".
            if (! $depth->includesChapters()) {
                foreach ($act->chapters as $chapter) {
                    $this->addChapterCoverPage($book, $chapter, $project, $settings);
                }

                $actXhtml = $this->renderActWithChapters($act, $project, $settings, $numbering);
                $this->assertXmlWellFormed($actXhtml, $actFile);
                $book->addChapter($this->actNavTitle($act, $numbering), $actFile, $actXhtml);

                continue;
            }

            // "Chapters" (default) and "Scenes": the act divider is its own page and each
            // chapter a nested page. Well-formedness gate BEFORE the document is buried in
            // the package, so a malformed page (a generator bug) is caught at its source.
            $actXhtml = $this->renderAct($act, $project, $settings, $numbering);
            $this->assertXmlWellFormed($actXhtml, $actFile);
            $book->addChapter($this->actNavTitle($act, $numbering), $actFile, $actXhtml);

            // Descend into the Act's nav entry so its Chapters nest underneath it.
            $book->subLevel();

            foreach ($act->chapters as $chapter) {
                // The chapter's cover page, if any, is a nav SIBLING of the
                // chapter — nested at the same level under the Act, immediately before the
                // chapter's own entry — rather than a child of it, so it never disturbs the
                // "Scenes" depth's own subLevel() of per-scene anchors below.
                $this->addChapterCoverPage($book, $chapter, $project, $settings);

                $chapterFile = $this->chapterFileName($chapter);
                $chapterXhtml = $this->renderChapter($chapter, $project, $settings, $numbering);
                $this->assertXmlWellFormed($chapterXhtml, $chapterFile);
                $book->addChapter($this->chapterNavTitle($chapter, $format, $numbering), $chapterFile, $chapterXhtml);

                // "Scenes" depth: hang a third nav level of per-scene entries under the
                // chapter. Each is added with NULL content and a "#"-bearing filename, which
                // the library registers as a nav entry pointing at an in-page anchor without
                // adding a new spine page — the scene lives inside its chapter document,
                // anchored by the id="scene-{id}" that chapter-body.blade.php emits.
                if ($depth->includesScenes()) {
                    $book->subLevel();

                    foreach ($chapter->scenes as $scene) {
                        $book->addChapter(
                            $this->sceneNavTitle($scene),
                            $this->sceneAnchorHref($chapter, $scene),
                            null
                        );
                    }

                    $book->backLevel();
                }
            }

            // Back to the root level before the next Act.
            $book->backLevel();
        }
    }

    /**
     * Add one Chapter's full-page cover image as its own spine page + nav entry,
     * immediately preceding wherever the caller is about to add that chapter's own content
     * (see the two call sites in {@see addBody()} for how "preceding" plays out at each
     * {@see TableOfContentsDepth}). A true no-op — added or not, this never disturbs the
     * caller's own subLevel()/backLevel() bracketing — when:
     *   - `include_chapter_covers` is off (the default), or
     *   - the Chapter has no `cover_image`, or
     *   - the cover file no longer exists on the `public` disk (mirrors {@see applyCover()}'s
     *     silent skip of a missing project cover — never fatal).
     *
     * The image bytes are embedded via the library's generic addFile() (manifest-only, no
     * spine/nav entry of its own) rather than setCoverImage(), which is reserved for the ONE
     * package-level cover {@see applyCover()} already owns. The image path is namespaced by
     * the chapter's stable id so it can never collide with the project cover's own "images/"
     * entry or another chapter's.
     */
    private function addChapterCoverPage(EPub $book, Chapter $chapter, Project $project, PublicationSetting $settings): void
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
            'language' => $this->language($project),
        ])->render();
        $coverFile = $this->chapterCoverFileName($chapter);
        $this->assertXmlWellFormed($coverXhtml, $coverFile);

        $book->addFile($imagePath, 'chapter_cover_'.$chapter->id, $bytes, $mimeType);
        $book->addChapter('Cover', $coverFile, $coverXhtml);
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
     * The nav label for an Act: "Act {number}", with ": {name}" appended when the Act has
     * a name (mirroring the page heading + name shape). `number` is the project-wide,
     * gap-free rank from {@see StoryNumbering} — see {@see actViewData()}.
     */
    private function actNavTitle(Act $act, StoryNumbering $numbering): string
    {
        $number = $numbering->act($act);

        return filled($act->name)
            ? "Act {$number}: {$act->name}"
            : "Act {$number}";
    }

    /**
     * The nav label for a Chapter, formatted by the configured {@see ChapterTitleFormat}
     * — the single source of truth shared with the chapter page heading, so the two can
     * never drift.
     *
     * With one addition a label needs and a heading does not: the `Title` format returns
     * an empty string for a chapter with no name, which is right on the page (the writer
     * asked for the title alone, and there isn't one) and useless in a contents listing,
     * where it renders as a blank row. `validatePackage()` schema-checks the OPF, not the
     * nav, so such a book exports clean and only looks broken in the reader.
     *
     * The fallback lives here rather than in `format()` on purpose: it is the *listing*
     * that cannot cope with an empty label, and it matches what {@see sceneNavTitle()} and
     * {@see actNavTitle()} already do with a nameless scene or act.
     *
     * `number` is the project-wide, gap-free rank from {@see StoryNumbering} — see
     * {@see chapterViewData()}.
     */
    private function chapterNavTitle(Chapter $chapter, ChapterTitleFormat $format, StoryNumbering $numbering): string
    {
        $number = $numbering->chapter($chapter);
        $label = $format->format($number, $chapter->name);

        return $label !== '' ? $label : "Chapter {$number}";
    }

    /**
     * The nav/TOC label for a Scene at "Scenes" depth: the Scene's name, falling back to
     * "Scene {position}" when it has none (blank/whitespace-only counts as none).
     */
    private function sceneNavTitle(Scene $scene): string
    {
        return filled($scene->name)
            ? $scene->name
            : "Scene {$scene->position}";
    }

    /**
     * The in-package fragment href a "Scenes"-depth nav/TOC entry points at: the scene's own
     * chapter page plus the `#scene-{id}` anchor that chapter-body.blade.php emits. Single
     * source of truth shared by {@see renderToc()} and {@see addBody()} so the two can
     * never point at a different anchor than the chapter page actually carries.
     */
    private function sceneAnchorHref(Chapter $chapter, Scene $scene): string
    {
        return $this->chapterFileName($chapter).'#scene-'.$scene->id;
    }

    /**
     * The in-package filename for an Act's page. Keyed off the stable database id (not
     * position) so two same-positioned entries in different parents can never collide.
     * Single source of truth shared by {@see addBody()} and {@see renderToc()} so
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
     * The in-package filename for a Chapter's cover page. Distinct from
     * {@see chapterFileName()} — the cover is a separate spine document, added only when
     * {@see addChapterCoverPage()} decides the chapter actually has one.
     */
    private function chapterCoverFileName(Chapter $chapter): string
    {
        return "chapter-cover-{$chapter->id}.xhtml";
    }

    /**
     * The in-package filename for one codex appendix entry page. Keyed off the
     * entry's stable database id so two entries can never collide. See {@see actFileName()}.
     */
    private function appendixEntryFileName(CodexEntry $entry): string
    {
        return "appendix-entry-{$entry->id}.xhtml";
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
            // SmartPunctExtension does dashes, ellipses and smart quotes together. It
            // lives ONLY on this instance — never on Str::markdown or
            // Scene::renderedContents.
            $converter->getEnvironment()->addExtension(new SmartPunctExtension);
            // Strikethrough/TaskList keep this isolated converter in agreement with the
            // GFM grammar Scene::renderedContents() and ValidMarkdown already use — added
            // individually (not via GithubFlavoredMarkdownConverter) so the SmartPunct
            // isolation rationale above stays intact: this instance never becomes shared.
            $converter->getEnvironment()->addExtension(new StrikethroughExtension);
            $converter->getEnvironment()->addExtension(new TaskListExtension);

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
