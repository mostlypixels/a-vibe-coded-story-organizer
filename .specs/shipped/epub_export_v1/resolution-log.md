# Epub export (v1) — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and issues →
resolutions found while implementing and verifying this feature. The `plan-implementer` agent
appends here per task; `ship-plan` consolidates it. Read it before extending the feature.

## Feedback & decisions

- The spec named `grandt/phpepub` implicitly by convention only (it wasn't actually named in
  the source spec) — during the second grill this was checked live against Packagist and
  found dead since 2016 (PHP ≥5.3, no PHP 8 support). Replaced with `rampmaster/phpepub`
  (PHP 8.2+, LGPL 2.1, actively maintained fork, ~259 installs as of 2026-07-12) after also
  rejecting `andileco/php-epub` (published one day prior to this grill, 5 installs, unproven).
- "Nothing to export" (empty filtered act/chapter tree) is handled as a service-thrown
  `EpubExportException` caught by the controller, not duplicated as Form Request validation
  logic — keeps the tree-filtering logic in one place (`EpubExporter`).
- Cover image handling is inline private `ProjectController` methods, not a new
  `ProjectCoverService` — only one call site exists in v1 (CLAUDE.md: no abstraction before a
  second caller).
- EPUB schema files for the PHP-native structural validation pass are vendored from the
  `epubcheck` project's own repository (confirmed permissively licensed) rather than sourced
  directly from W3C/IDPF pages, whose redistribution terms weren't conclusively found.
- Smart typography (dashes, ellipsis, smart quotes via CommonMark's `SmartPunctExtension`) is
  deliberately isolated to the epub export path — `Scene::renderedContents`, used by the
  Story overview, share page, and the existing `book/` export, must remain unaffected. Every
  task touching typography must include a test proving this isolation.

- **Task 06 — the empty-project error is keyed to `project_id`.** The controller catches
  `EpubExportException` and does `redirect()->back()->withErrors(['project_id' => $message])`.
  Keying the bag to `project_id` (the only field the epub form posts) means task 07's form can
  surface it with the same `@error('project_id')` convention the rest of the app uses, right by
  the project selector. Task 07 should render that error next to its project `<select>`.

- **Task 07 — the two forms share the `project_id` *name* but not the element `id`.** The
  epub picker uses `id="epub_project_id"` (name still `project_id`) so the page keeps unique
  DOM ids while both forms post the same field name (grilled two-forms decision). The empty-state
  test still asserts `assertDontSee('name="project_id"')`, which holds because both forms live
  only inside the `@else` (non-empty) branch.

## Deviations from the spec/plan

- **Task 04 — installed library API differs from the planning summary.** `rampmaster/phpepub`
  `1.1.1` (class `Rampmaster\EPub\Core\EPub`) was verified against its real source, not the
  summarized description. Confirmed real method names: `setTitle`, `setLanguage`,
  `setIdentifier($id, $type)` (types `EPub::IDENTIFIER_URI|ISBN|UUID`), `setAuthor($name,$sortAs)`,
  `setPublisher($name,$url)`, `setRights`, `setCoverImage($fileName,$imageData,$mimetype)`,
  `addCSSFile($name,$id,$data)`, `addChapter($title,$file,$xhtml)`, `subLevel()`/`backLevel()`
  for nested nav, `setAccessibilitySummary`/`addAccessMode`/`addAccessibilityFeature`, and
  `getBook()` (returns finalized epub bytes). The EPUB 3 nav document is auto-built during
  `finalize()` (`buildEPub3TOC` → `epub3toc.xhtml`); `buildTOC()` is not called. The OPF is
  written to `OEBPS/book.opf`.
- **Task 04 — ISBN scheme expressed as `urn:isbn:` in EPUB 3, not `opf:scheme="ISBN"`.** The plan
  named an `opf:scheme="ISBN"` attribute. The library only renders `opf:` attributes for EPUB 2
  (`MetaValue::finalize` gates them on `BOOK_VERSION_EPUB2`); this feature builds EPUB 3 (XHTML5
  content + EPUB 3 nav + `schema:` accessibility meta all require it). The EPUB-3-idiomatic scheme
  expression is a second `<dc:identifier>urn:isbn:{isbn}</dc:identifier>`, added via
  `addCustomMetaValue(new DublinCore(DublinCore::IDENTIFIER, ...))`. The generated
  `urn:imagoldfish:project:{id}` URN remains the package's unique identifier — the ISBN is
  additive, never a replacement.

- **Task 02 — cover field name.** The plan named the file input `cover_image` (matching
  the column). Kept that name for both the file input and the validation key; the remove
  checkbox is `remove_cover_image` (mirrors Codex's `remove_media[]` but for the single
  column). The Codex partial uses `cover`/`remove_media[]`; the Project form intentionally
  diverges so the request key equals the column, avoiding a controller rename step.
- **Task 02 — no shared service extracted.** Cover store/delete stayed as inline private
  methods (`storeCoverImage`/`deleteCoverImage`) on `ProjectController` plus orchestration
  in `update()`, per the grilled "no abstraction before a second caller" decision. Stored
  under `project-covers/` on the `public` disk.

## Issues → resolutions

- **Task 04 — `dc:language` silently downgraded to `en` for region-tagged BCP-47 codes.**
  `EPub::setLanguage()` guards on `mb_strlen($language) != 2` and silently returns `false` for
  anything longer, so `fr-CA` / `en-US` never reach the OPF — `finalize()` then emits the
  library default `<dc:language>en</dc:language>`. A green test would have missed this for the
  common two-letter case; it only surfaced because a test asserted `fr-CA`. Root cause: the
  library's over-strict length check. Fix: after `getBook()`, `EpubExporter::correctOpfLanguage()`
  reopens the epub and rewrites the single `dc:language` in `OEBPS/book.opf` to the project's full
  language (idempotent for two-letter codes). The content documents were already correct — this
  service renders their `lang`/`xml:lang` itself, independent of the library.
- **Task 05 — nav is NOT schema-validated (well-formedness only); OPF is.** The task scoped
  RelaxNG/XSD schema validation of *both* the OPF and the EPUB 3 nav document via
  `DOMDocument`. Investigation showed this is only feasible for the OPF. epubcheck ships no
  XSD (so `schemaValidate()` is unusable) and only RelaxNG **compact** (`.rnc`) schemas, which
  libxml cannot read — so schemaValidate was replaced with `relaxNGValidate()` against `.rng`
  (RelaxNG XML) files converted once from epubcheck's `.rnc` with trang (build-time only; no
  JVM at runtime). The **OPF** grammar (`package-30.rnc` + 2 tiny includes) converts cleanly
  and libxml validates real OPFs both ways (valid→pass, missing-`<metadata>`→fail). The **nav**
  grammar (`epub-nav-30.rnc`) transitively pulls the *entire* XHTML5 + MathML3 + SVG RelaxNG
  tree (100+ files across `mod/html5/`, `mod/mathml/`, `mod/svg/`), which libxml's RelaxNG
  engine cannot process — and since a schema failure throws a 500, a validator that false-fails
  on valid output is worse than none. So the nav (and every content/cover XHTML) is covered by
  the well-formedness (`loadXML`) gate instead; only the OPF is schema-validated. Rationale is
  recorded in `resources/epub-schemas/README.md`.
- **Task 05 — `<dc:source>` normalized to `config('app.url')`, resolving task 04's heads-up.**
  The library's `finalize()` derives `dc:source` from the request environment
  (`getCurrentServerURL()`): the real server URL under HTTP, the malformed `http://:/` under
  CLI/queue. It IS schema-valid (dc:source is free text — the malformed value passes
  `package-30.rng`), so validation does not force the change. It was normalized anyway so an
  export is deterministic regardless of execution context and never ships `http://:/`. The
  library's `setSourceURL()` API can't be used for this: `finalize()` unconditionally
  overwrites `sourceURL` whenever the publisher URL is empty (a library bug — it tests
  `publisherURL` but assigns `sourceURL`), and this service always passes an empty publisher
  URL. So the value is rewritten in the finalized OPF, folded into the existing
  language-correction reopen (`correctOpfLanguage` → `normalizeOpf`).

- **Task 04 — content documents are passed to `addChapter()` whole, not as fragments.**
  `addChapter()` writes the given string verbatim as `application/xhtml+xml` (it does NOT wrap it
  like `addReferencePage()` does, and `encodeHTML` defaults to `false`), so this service hands it
  the complete XHTML5 documents from task 03's Blade layout rather than bare `<body>` fragments —
  matching what task 05's `DOMDocument::loadXML()` will parse.
- **Task 04 heads-up for task 05 — library auto-injects `<dc:source>`.** When `sourceURL` is empty,
  `finalize()` fills it from `URLHelper::getCurrentServerURL()`. Under an HTTP request that is the
  real server URL; under CLI/queue it is the malformed `http://:/`. Not addressed in task 04
  (out of scope), but task 05's OPF schema-validation pass should decide whether to set a real
  `sourceURL` (e.g. the app URL / project route) or accept the value — flagging so it is not a
  surprise.

- **Task 02 — cover excluded from mass-assign:** `$request->validated()` includes the
  `cover_image` as an `UploadedFile`, so `$project->update($request->validated())` would try
  to assign the object to the path column. Fixed by updating with
  `$request->safe()->except(['cover_image', 'remove_cover_image'])` and setting the resolved
  string path (or `null`) separately. The old file is deleted only *after* a committed save;
  a new upload is unlinked if the row write throws (mirrors `CodexMediaService::store()`).
- **Task 02 — tinker render needs an errors bag:** rendering `projects.edit` via
  `Blade`/`view()->render()` in `artisan tinker` fails with "Call to a member function
  get() on null" because `$errors` is normally shared by the `ShareErrorsFromSession`
  middleware, which tinker bypasses. Not a code bug — for the manual render check,
  `View::share('errors', new ViewErrorBag)` first. The HTTP-stack feature tests are
  unaffected.
- **Task 01 — mass-assignability test:** `user_id` is deliberately *not* in `Project::$fillable`
  (associations are set via the factory/relationship), so a `Project::create([...])` that
  passes `user_id` fails the NOT NULL constraint. Root cause: assuming the FK was fillable.
  Fixed by creating the project via `Project::factory()->for($user)` and then `fill([...six
  new columns...])->save()` — which still proves the six new columns are mass-assignable
  without touching the non-fillable association.
- **Task 03 — smart-quote isolation assertion escaping:** the first cut of the typography
  isolation test asserted `Scene::renderedContents` still contained the literal `"quotes"`.
  It doesn't — `Str::markdown` HTML-escapes `"` to `&quot;`. Root cause: the shared render
  *does* leave quotes straight (the point being proven), but they're HTML-entity-escaped, not
  raw. Fixed the assertion to look for `&quot;quotes&quot;`; the em-dash/curly-quote
  *negative* assertions (the actual isolation proof) were already correct.
- **Task 03 — XML well-formedness of the `<?xml?>` declaration:** to keep PHP from ever
  treating `<?xml` in the Blade layout as a short-open tag, the declaration is emitted via
  `{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}` rather than typed literally. Verified by
  rendering both content documents and parsing each with `DOMDocument::loadXML()` (task 05's
  precondition) — both parse clean, with CommonMark self-closing void elements (`<hr/>`,
  `<br />`) and `&amp;`-escaped ampersands keeping the fragments XML-valid.
