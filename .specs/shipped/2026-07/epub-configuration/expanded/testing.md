# Epub Configuration — Testing

Plain PHPUnit, `use RefreshDatabase`, factories, `actingAs($user)`, the `route()` helper — the
established style (`tests/Feature/ProjectTest.php`). In-memory SQLite via `composer test`.

> [!WARNING]
> **SQLite JSON + deep-nesting caveats.** `appendix_entry_types` is a JSON column and the appendix
> query filters on it — assert via the model, not raw SQL, and avoid deep nested `REPLACE()`/JSON
> expressions that overflow CI's SQLite parser (see the project memory on parser depth). The
> appendix HTML-normalisation must be tested against SQLite-stored rich HTML specifically.

## New / updated feature tests

### `EpubSettingTest` (new — the config CRUD)
- **Save happy path:** owner PATCHes `admin.data.epub-settings.update`; row is created with the
  posted toggles/enums/Markdown; redirect + status flash.
- **Update existing:** second save updates the same row (no duplicate; unique `project_id`).
- **Authorization:** non-owner PATCH → **403**; guest → redirect to login. Mirror the
  Request-level check (a foreign `project` binding is 403 before the action runs).
- **Validation:** invalid enum value → `assertSessionHasErrors('divider_type')`; bad Markdown in
  `dedication` → `assertSessionHasErrors('dedication')` (proves `ValidMarkdown` is wired);
  non-boolean toggle rejected; `appendix_entry_types` containing an unknown type rejected.
- **Markdown fields persist on the project** (data-model Q1) and reload into the form.

### `EpubExporterTest` (extend the existing service test)
Each config choice must **measurably** change the packaged EPUB while it still passes the
existing well-formedness + OPF-schema gate (`validatePackage()` already runs inside `export()`,
so a schema regression fails the test automatically). Unzip the produced `.epub` and assert on
entry contents.

- **Regression — defaults === v1 (highest priority):** a project with a default `EpubSetting`
  (or none, via `epubSettingOrDefault()`) produces the current output — `"Chapter n: name"`
  heading, `<hr/>` dividers, no scene titles, no descriptions, no front/back matter, no appendix,
  metadata emitted when present. Guard this so the config work can't silently change existing
  books.
- **Chapter title format:** each `ChapterTitleFormat` case yields the expected heading **and** the
  matching TOC/nav label (single source of truth); name-less chapter has no dangling `": "`.
- **Scene titles / descriptions / act & chapter descriptions:** toggling each on inserts the text;
  off omits it. Empty content + toggle on ⇒ **no** blank element.
- **Divider:** `decorative` swaps `<hr/>` for the ornament; still well-formed.
- **TOC depth:** `Acts` → nav/TOC has no chapter children; `Chapters` → today's two levels;
  `Scenes` → scene anchors exist (`#scene-{id}`) and resolve to real ids in the chapter document.
- **Metadata toggles:** `include_author=false` with a set author ⇒ OPF has **no** `dc:creator`;
  same for publisher/rights/isbn. Title + primary URN identifier + accessibility metadata always
  present. ISBN off ⇒ no `urn:isbn:` identifier but the generated URN stays.
- **Cover toggle:** `include_project_cover=false` ⇒ no cover manifest item even when the project
  has a cover image.
- **Front/back matter:** enabled + non-empty ⇒ dedication/preface/postface/acknowledgements pages
  present in the right spine positions; SmartPunct typography applied (proves the service
  converter, not `Scene::renderedContents`); disabled or empty ⇒ absent.
- **Appendix:** selected types only; ordered; `appendix_include_images` embeds the first media
  image (and a missing file is skipped, not fatal); rich-HTML entry description is normalised to
  **well-formed XHTML** (feed it deliberately non-XHTML sanitized HTML and assert the package
  still validates).

### `DataTransferTest` / routing (split page)
- Each of the three GET pages renders for an authed user (`assertOk`, sees its own controls),
  and `admin.data.index` redirects to `export-project`.
- Sub-nav marks the current page `aria-current="page"`; the sidebar "Export & import" entry stays
  active on all three.
- The **`.zip` export and Import flows still pass their existing tests unchanged** — run
  `ExportTest` / `ImportTest` / `ImportSettingTest` as-is; if they asserted against the old tabbed
  `index` markup, update those assertions to the new page URLs (behaviour identical).

### Import/export round-trip (`ProjectImporter` / `StaticSiteExporter` tests)
- **The four Markdown columns survive a round-trip:** export a project with dedication/preface/
  etc., re-import, assert equality. This is the change most likely to be forgotten — see
  data-model.md. Add manifest-version handling: an archive **without** the new fields imports with
  `null`s (no crash).
- Decide (Q1/Q8) whether `EpubSetting` itself is exported/imported; if yes, add its round-trip
  test; if no, document that export configs don't travel with the archive.

## Coverage checklist (CLAUDE.md minimums, per new endpoint)
Happy path ✔ · owner-succeeds / non-owner-403 ✔ · validation failures (`assertSessionHasErrors`)
✔ · touched invariants (defaults===v1, front-matter-empty-⇒-no-page, appendix-empty-types) ✔.
