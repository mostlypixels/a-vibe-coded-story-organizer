# Epub export (v1) — architecture

## Routes & controller

New route, sibling to the existing export route in `routes/web.php`:

```php
Route::post('/data/export/epub', [EpubExportController::class, 'store'])->name('data.export.epub');
```

`app/Http/Controllers/EpubExportController.php` — thin, mirrors
`app/Http/Controllers/ExportController.php` exactly:

```php
public function store(EpubExportRequest $request, EpubExporter $exporter): BinaryFileResponse
{
    $project = Project::findOrFail($request->integer('project_id'));
    $this->authorize('view', $project);

    $epubPath = $exporter->export($project);

    $filename = Str::slug($project->name).'-'.now()->format('Ymd-His').'.epub';

    return response()
        ->download($epubPath, $filename, ['Content-Type' => 'application/epub+zip'])
        ->deleteFileAfterSend(true);
}
```

`app/Http/Requests/EpubExportRequest.php` mirrors `ExportRequest`: `authorize()` walks
`ProjectPolicy@view` via `project_id`, `rules()` validates `project_id` exists — no
`include_images`-equivalent option in v1 (no image toggle; the epub never embeds Codex media).

## Service: `EpubExporter`

`app/Services/EpubExporter.php` — HTTP-agnostic, analogous to `StaticSiteExporter`. Given a
`Project`, returns a path to a generated `.epub` file in a temp location.

Responsibilities, in order:

1. **Load the tree** — `$project->load('acts.chapters.scenes')`, ordered by `position`
   (same eager-load shape as `StaticSiteExporter::loadBookTree()` — reuse or extract a shared
   private method if the duplication becomes annoying; not required for v1).
2. **Filter** — drop Chapters with zero Scenes, then drop Acts with zero surviving Chapters
   (grilled decision). If nothing survives, throw a dedicated exception
   (`EpubExportException` or similar) that the controller/Form Request turns into a
   validation error — see `testing.md`.
3. **Render content** — for each surviving Act, an XHTML divider page; for each surviving
   Chapter, an XHTML page with its Scenes' Markdown compiled to HTML and joined by `<hr>`.
   Rendering is Blade, not string-built PHP (matching the `book/` layer's own rule) — new
   views under `resources/views/exports/epub/` (`act`, `chapter`, `nav`, `package` or
   similar — one file per document type).
4. **Apply epub-only typography** — a dedicated `CommonMarkConverter` instance configured
   with `League\CommonMark\Extension\SmartPunct\SmartPunctExtension` (dashes, ellipsis, and
   quotes together, per the grilled decision), used only inside this service — **never**
   touching `Scene::renderedContents` (the shared render path used by the Story overview,
   share page, and the existing `book/` export).
5. **Build the package** — hand the rendered documents plus metadata (title, language,
   author, publisher, rights, ISBN, cover, generated URN identifier, accessibility metadata)
   to the chosen epub-packaging library (see below) to produce the container, OPF, and
   nav/NCX, then zip it to a temp path.
6. **Validate structurally** — see Validation below — before returning the path.

## Epub packaging library

Per the grilled decision: use a maintained composer package for OPF/NCX/mimetype/zip
conformance rather than hand-rolling `ZipArchive` calls. Candidate: `grandt/phpepub`. Before
committing to it in the plan stage, confirm on Packagist that it is still maintained and
PHP 8.x-compatible (not verified during this grill) — if it's stale, the fallback is a
different actively-maintained epub-packaging package, not a hand-rolled implementation, to
keep the "don't reimplement EPUB conformance by hand" rationale intact.

The library owns: `mimetype` file (stored uncompressed, first entry), `META-INF/container.xml`,
the OPF package document (`content.opf` — metadata, manifest, spine), and the EPUB 3 nav
document. Our code owns: the actual XHTML content (via Blade), the metadata values fed into
the library's builder API, and the CSS file.

## CSS

One stylesheet (`resources/views/exports/epub/styles.css` or similar, referenced by every
XHTML content document): only `break-before: page` (and legacy `page-break-before: always`
for older reader engines) rules on Act and Chapter root elements. No fonts, colors, or
spacing — per the grilled "semantic HTML + minimal page-break CSS only" decision.

## Accessibility metadata

In the OPF `<metadata>` block, alongside the Dublin Core fields:

```xml
<meta property="schema:accessibilityFeature">structuralNavigation</meta>
<meta property="schema:accessMode">textual</meta>
<meta property="schema:accessibilitySummary">Text-only publication with structural navigation via table of contents.</meta>
```

Every generated XHTML document's root element carries `xml:lang="{Project.language}"
lang="{Project.language}"`.

## Validation (PHP-native, no Java)

Per the grilled decision, no `epubcheck`/JVM dependency. Two cheap, PHP-native checks run
inside `EpubExporter` before the file is returned:

1. **Well-formedness** — every generated XHTML fragment is parsed with
   `DOMDocument::loadXML()` (libxml internal errors captured, not suppressed) before being
   handed to the packaging library. Catches unclosed tags / unescaped `&` surviving from
   markdown edge cases.
2. **Schema validation** — the generated OPF and nav documents are validated against the
   published EPUB 3 XSD/RelaxNG schemas via `DOMDocument::schemaValidate()` (bundle the
   schema files under, e.g., `resources/epub-schemas/` — confirm licensing/redistribution
   terms for the IDPF/W3C schemas during planning).

A failure in either step is a **server-side bug**, not a user input problem — it should throw
loudly (500, logged) rather than silently degrading, since it means the generator itself
produced non-conformant output.

On the export page itself, a static note near the new epub section links to
https://www.w3.org/publishing/epubcheck/, telling authors to run the official validator
themselves for full conformance confidence before submitting to a retailer — this is UI copy,
not a controller behavior; see `ui.md`.

## Authorization

Identical pattern to the existing export: `EpubExportController::store()` calls
`$this->authorize('view', $project)`; `EpubExportRequest::authorize()` mirrors the same check
via `ProjectPolicy`. A non-owner posting a foreign `project_id` gets a 403, matching
`ExportRequest`'s existing documented rationale (the `/admin` gate is "any authenticated
user," not ownership).

## `ProjectController` changes

`UpdateProjectRequest` (`app/Http/Requests/UpdateProjectRequest.php`) gains rules for the six
new fields:

```php
'language' => ['required', 'string', 'max:10'],
'author' => ['nullable', 'string', 'max:255'],
'publisher' => ['nullable', 'string', 'max:255'],
'rights' => ['nullable', 'string', 'max:1000'],
'isbn' => ['nullable', 'string', new ValidIsbn],
'cover_image' => CodexMediaRules::coverRules(), // plus a remove-cover checkbox, Codex-style
```

`ValidIsbn` (new, `app/Rules/ValidIsbn.php`) — strips hyphens/spaces, checks 13 digits and the
ISBN-13 checksum, matching the `ValidMarkdown`-style single-purpose `ValidationRule` class
already established in `app/Rules/`.

`ProjectController::update()` gains the small amount of cover-image handling logic (store new
file / delete old file on replace or explicit removal) — following CLAUDE.md's guidance to
keep this in a private controller method or a small service until a second caller exists;
given the deliberate "no CodexMedia-style table" decision, a full service class may be
over-engineering for a single nullable path column — a private method or a two-method
`ProjectCoverService` are both reasonable; leave the final call to the plan stage.
