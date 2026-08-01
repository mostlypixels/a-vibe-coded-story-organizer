# Architecture

This is a Laravel 13 app (PHP 8.5, Breeze auth, Blade + Tailwind, Alpine.js — no SPA framework)
for tracking a writing project's plotlines, timeline events, and manuscript structure.

## Domain model

```
User
 └── Project                (belongs to a user)
      ├── Plotline          (one is the "main plotline")
      ├── Event             (many-to-many with Plotline)
      └── Act
           └── Chapter
                └── Scene
```

- A `User` has many `Project`s.
- Each `Project` has many `Plotline`s, `Event`s, and `Act`s.
- `Event` ↔ `Plotline` is many-to-many (an event can touch several plotlines).
- `Act` → `Chapter` → `Scene` is a strict three-level hierarchy (no many-to-many).

The manuscript hierarchy is an **aggregate** rooted at `Project`: you almost never load
a `Scene` in isolation without caring which `Project` owns it. That ownership root drives
authorization (below).

## Authorization flows from the Project

There is a single policy, `App\Policies\ProjectPolicy`, with three abilities — `view`,
`update`, `delete` — each checking `$user->id === $project->user_id`.

Child resources do **not** have their own policies. Instead each controller walks up to the
owning project and authorizes against it:

```php
// SceneController@edit
$this->authorize('update', $scene->chapter->act->project);
```

Form Requests mirror the same check in their `authorize()` method.

> [!IMPORTANT]
> Every action that reads or writes a resource must authorize through the project. If you
> add a new child controller, authorize via `->...->project`, and add a test proving a
> non-owner gets a `403`. Route model binding alone is **not** access control.

## The main plotline invariant

Every `Project` auto-creates one special `Plotline` (`is_main = true`, name "Main plotline")
in a `Project::booted()` `created` hook. This plotline **cannot be deleted** —
`PlotlineController@destroy` calls `abort_if($plotline->is_main, 403)`.

> [!WARNING]
> Any UI or logic that lists plotlines must account for the main plotline being
> un-deletable, and it should generally stay pinned first in listings.

## Act / Chapter / Scene ordering

Each of `Act`, `Chapter`, `Scene` has a `position` integer, auto-assigned as
`max(position) + 1` scoped to its parent (project for acts, act for chapters, chapter for
scenes) via a `creating` hook in the model's `booted()` method.

- Titles are freeform and must **not** encode the number (no "Act 1" in the name). The
  position is the number, rendered separately in a `#` column.
- Reordering swaps `position` with the adjacent sibling via `moveUp` / `moveDown` controller
  actions (`PATCH /acts/{act}/move-up`, etc.). There is no drag-and-drop.
- Index views only show move buttons when the list is genuinely ordered by position for a
  single parent (i.e. filtered to one act/chapter), because numbering restarts per parent.

> [!WARNING]
> **Seeding caveat.** `DatabaseSeeder` uses `WithoutModelEvents`, which suppresses the
> `creating` hook. `MelusineSeeder` therefore sets `position` explicitly (and creates the
> main plotline manually) — if you add seeded acts/chapters/scenes, set `position` yourself.

## Routing (shallow nested resources)

Nested resource routes use Laravel's shallow nesting:

```php
Route::resource('projects.scenes', SceneController::class)->shallow();
```

- `index` / `create` / `store` are nested under `/projects/{project}/...`.
- `edit` / `update` / `destroy` are flat (`/scenes/{scene}`) — the child model alone
  resolves the route.
- Acts/chapters/scenes additionally have flat `PATCH .../move-up` and `.../move-down` routes.
- All routes require the `auth` middleware.

## Story overview

`StoryController@index` (`GET /projects/{project}/story`) is a read-only page combining the
full act/chapter/scene tree. Chapters render as `<article>`, scenes as `<section>`, and
`Scene::contents` is rendered as Markdown via the `Scene::renderedContents` accessor (which
wraps `Illuminate\Support\Str::markdown()` and the null-guard). That accessor is the **single
home** for the render choice: the Story overview, the public share view, and the book export
all read `$scene->renderedContents` so they can never render scene contents differently.

> [!NOTE]
> `Str::markdown()` is backed by `league/commonmark`, which is present as a **transitive**
> dependency of `laravel/framework` (via `composer.lock`), not in `composer.json`'s own
> `require`. Don't assume it survives a dependency prune without checking.

## Scene sharing (public read-only links)

A scene can be shared with someone who has **no account** via an opaque, revocable link.
Two nullable columns on `scenes` back it: `share_token` (unique, stored raw) and
`share_expires_at`. The owner generates/rotates the link from the scene edit page
(`SceneShareController`, authenticated, authorizes up to the project like every other
scene action); a visitor opens it at `GET /shared/scenes/{token}`.

That public route is **the one deliberate exception** to "every action authorizes through
the project" and "every route is authenticated":

- It lives **outside** the `auth` middleware group (the only unauthenticated app route
  besides `welcome` and the Breeze auth screens — do not widen the group to reach it).
- `SharedSceneController@show` has **no `authorize()` call**. The token *is* the
  authorization: whoever holds a live token may read the scene. This is commented in the
  controller so a reviewer does not "fix" it.
- The token is bound as a plain **string** (not route-model binding) so the controller
  chooses the response: an unknown token → `404`, an expired/revoked token → a friendly
  branded `410` page (`shared/scenes/expired.blade.php`), a live token → the read-only page.

> [!WARNING]
> **Validity is `Scene::isShared()`, never "a token exists".** A token alone is not access:
> `isShared()` also requires the expiry to be in the future, so a leaked-but-expired URL is
> inert server-side. The 410 page renders **no scene data** — an expired link must not leak
> the title/description/contents it once granted.

> [!IMPORTANT]
> **`scene.notes` is private.** The public page renders only `name`, `description` (collapsed
> card, via `x-rich-text`) and `contents` (`Str::markdown()`). It **never** renders `notes`,
> the status, or the event/plotline links; a test asserts `notes` never appears in the HTML.
> The page uses a dedicated no-nav `<x-public-layout>` whose `<head>` carries the
> `<x-robots-meta :force="true" />` component (see *Hidden from crawlers* below) so forwarded
> links stay unindexed regardless of the global toggle.

## Hidden from crawlers (robots.txt + noindex)

The whole site can be hidden from search engines through a single global toggle, plus a
whitelist of crawlers that stay allowed while hidden. It is **advisory only** (robots.txt +
`noindex` meta tags) — there is no request-layer bot blocking, firewall, or UA denylist.

**The singleton.** `CrawlerSetting` is one application-wide row (one website → one robots
policy). It has **no owning `Project` or `User`** — it is global. Always read it through
`CrawlerSetting::current()`, which lazily creates the row from `config('crawlers.default_enabled')`
on first read, so a fresh install with no row still behaves as **hidden** (the safe default).
Never `new` a second row. `current()` is deliberately **not memoised** — the value can change
within a request (settings update then robots fetch) and the single-row query is trivial.

> [!NOTE]
> The "default hidden" value lives in **two** places by design: the `crawler_settings.enabled`
> column default and `config('crawlers.default_enabled')`. The config value is the documented
> source of truth (seeds the lazy-create path); the column default is a backstop for direct
> inserts. Keep the two equal.

**Dynamic `/robots.txt`.** A public route (`RobotsTxtController`, outside the `auth` group,
next to `shared.scenes.show`) renders robots.txt live from the settings via
`RobotsTxtGenerator`. When hidden it emits one `User-agent: <term>` allow-group per whitelisted
crawler, then a catch-all `User-agent: *` / `Disallow: /` block — exploiting that a compliant
crawler obeys only its most specific matching group. When not hidden it allows everyone.

> [!WARNING]
> **The static `public/robots.txt` was removed** so the dynamic route is reached. A physical
> file in `public/` shadows the route under `php artisan serve` and typical nginx `try_files`.
> Do not re-add a static `robots.txt`. Whitelist terms are validated line-safe (no CR/LF, `:`,
> or `#`) on the write path — that regex is the single guard the generator trusts, so it does
> no escaping of its own.

**The `x-robots-meta` component.** `resources/views/components/robots-meta.blade.php` emits
`<meta name="robots" content="noindex, nofollow">` when the site is hidden (or when `:force`
is set). It is the single source of that string, wired into the `<head>` of `layouts/app`,
`layouts/guest`, `welcome` (all toggle-governed) and `layouts/public` (`:force="true"` — shared
scenes stay hidden regardless of the global toggle).

> [!WARNING]
> **Authorization exception.** `CrawlerSetting` is the one setting **not** owned by a `Project`,
> so it does **not** use `ProjectPolicy`'s walk. The settings screen sits behind `auth`, and
> `UpdateCrawlerSettingRequest::authorize()` returns `$this->user() !== null` — **any**
> authenticated user may edit it. This is deliberate (no `is_admin` role); do not "fix" it into
> a project walk.

## Revisions (autosave + entity history)

Fourteen long-text fields across the project tree (`Scene.contents` above all) autosave as
the writer types, and every save that matters is recoverable through history / compare /
undo. There is **no draft-vs-published split**: autosave writes the live column, so exports,
search, share links and `SceneReferenceMatcher` always read what the writer sees.

The feature works at two altitudes, and they deliberately do not match:

| | The unit | Where it lives |
|---|---|---|
| **Storage** | one immutable row **per field, per moment** | `revisions` table — append-only; only an explicit purge deletes |
| **Interface** | one **save point** per Save (or autosave burst), covering every field it touched | `revisions.save_id`, folded into `SavePoint`s by `RevisionHistory` |

Writers don't think *"I changed `Scene.notes` at 14:03"* — they think *"I saved"*. So every
screen is addressed by **entity + save point**; a single field is a `?field=` filter, not a
page of its own.

The load-bearing pieces:

- **`App\Support\AutosavableFields::REGISTRY`** — single source of truth for what autosaves,
  how routes resolve, and how each field validates.
- **`App\Services\RevisionRecorder`** — the only writer of `revisions` rows. Coalesces
  `automatic` saves within a window; every other origin always inserts.
- **`Revision::prunable()` (prune) vs `App\Services\RevisionPurger` (purge)** — the
  unattended sweep may only touch unlabeled `automatic` rows, and never a field's newest.
- **`RevisionDiffer`** — visual diff for TipTap HTML, source diff for Markdown/Plain.
- **`RevisionHistory` / `RevisionSnapshot` / `RevisionReverter`** — save-point folding,
  point-in-time state, and revert/undo (additive, all-or-nothing).

Rules that bite if you don't know them:

- No history query ever selects `revisions.value` — `size_bytes`, `summary_html` and
  `change_count` exist so it doesn't have to. A query-listener test guards this.
- `project_id` is always set explicitly, never inferred from the polymorphic pair.
- Never run diff output through `HtmlSanitizer`; it would eat the `<ins>`/`<del>` markers.
- Revert is additive. Nothing but an explicit purge ever deletes history.

> [!IMPORTANT]
> Before changing any of this, read **[`revisions.md`](revisions.md)** — the invariants,
> pitfalls and rejected alternatives in full — and
> `.specs/shipped/2026-07/revision-history-rework/standing-issues.md`, which holds the
> feature's **accepted costs** — known consequences of decisions, not bugs. Do not "fix"
> one without re-opening the decision it came from.

## Word count

A live counter in every prose field, per-scene counts on the story overview and the scene
index, and chapter / act / project totals on the overview, the act and chapter indexes and
the project header.

- **`scenes.word_count` is the only stored count.** Chapter, act and project totals are a
  `SUM` over it — benchmarked (widest gap versus denormalising every level: 0.6 ms at 4,320
  scenes / 6.3 M words), and the Story overview already eager-loads its scenes, so its totals
  cost nothing. Do not add a column to `chapters`, `acts` or `projects`.
- **Only `scenes.contents` is ever counted** — never `description`, never `notes`.
- `App\Support\WordCounter` is the one definition of "a word"; `x-word-count` the one place a
  count is formatted (including "0 words", which is never rendered blank).
- The column is kept true by a **`Scene` `saving()` hook**, so it survives autosave, manual
  save, revert/undo, import and seeding. Do **not** move it into a controller:
  `RevisionReverter` saves the model directly and never reaches `FieldAutosaveController`.
- Totals are aggregated in the controller (`withSum`, or `sum()` over already-loaded
  relations) — never with a `->sum()` inside a Blade loop, and there is deliberately no
  `wordCount()` accessor to tempt one.
- The in-field live counter is **indicative**, the server authoritative; they reconcile via
  `word_count` in the autosave response. Being approximate between saves is an accepted cost.

> [!IMPORTANT]
> Before touching any of this, read **[`word-count.md`](word-count.md)** — the counting rule
> in full, the bulk-write pitfall (`DB::table()`, never `$model->save()`), why seeding needs
> its own backfill, and the `withSum`-returns-`NULL` trap — and
> `.specs/shipped/2026-07/word-count/standing-issues.md`, which holds the feature's **accepted
> costs**: known consequences of decisions, not bugs. Do not "fix" one without re-opening the
> decision it came from.

## Enum convention

Enums live in `app/Enums`. The pattern (see `SceneStatus`):

- A **string-backed** enum with a `label()` method (via `match`) for display.
- Stored in a plain `string` DB column with a default — not a native DB enum.
- Cast on the model (`protected $casts = ['status' => SceneStatus::class]`).
- Validated in the Form Request with `Rule::enum(SceneStatus::class)`.
- Rendered through a dedicated Blade badge component (`scene-status-badge`).

## The Codex (characters, locations, organizations)

The **Codex** is a project-scoped reference aggregate for the story's entities. It reuses
every existing convention — authorization walks up to `Project`, shallow routes, Form
Requests, index filtering in the controller — and adds the project's **first `app/Services`
layer** for the one genuinely non-trivial piece: temporal attribute values.

The shape:

- **One table, one controller.** All three kinds live in `codex_entries` with a `type` column
  cast to `App\Enums\CodexEntryType`; the type is a route segment, and the route constraint,
  nav links and `fromRouteKey()` all derive from the enum.
- **Attribute values are a start-anchored step function.** Each row means "from this event
  onward, the value is X" — no stored end event, so periods tile the timeline with no holes or
  overlaps by construction.
- **Three services:** `AttributeTimeline` (temporal resolution and gap-free mutation),
  `CodexMediaService` (storage paths, the single-cover rule, deleting files off disk), and
  `SceneReferenceMatcher` (which entries a scene's prose mentions).

Rules that bite if you don't know them:

- **Every (entry, attribute) with any value has one anchored at the project's Start event**, so
  `valueAt(t)` is total and callers never handle "no value". `upsertAt` enforces it on every
  write path.
- **These are services, not `booted()` hooks** — hooks are suppressed under
  `WithoutModelEvents`, which the seeder and importer both use.
- **A DB cascade bypasses model hooks, so it bypasses file cleanup.** `Project::deleting` and
  `User::deleting` exist solely to close that leak.
- **`SceneReferenceMatcher` is always a full `sync()`** for its scope, never an incremental
  attach/detach — that is what stops the derived pivot from drifting.
- Disk I/O stays **outside** the entry-save transaction, deliberately, with an accepted
  trade-off.

> [!IMPORTANT]
> Before changing any of this, read **[`codex.md`](codex.md)** — the step function, the
> containment window that keeps Start earliest, the matching rules, and the reasoning behind
> each trade-off.

## Rich text (WYSIWYG)

Most free-text fields — every `description`, plus `Scene.notes` — are **rich HTML**, authored in
a Tiptap-backed WYSIWYG editor (`x-wysiwyg`) and rendered through `x-rich-text`. The field list,
the sanitizer allow-list, HTMLPurifier sanitization on write (per-field set-mutators, so the DB
never holds unsafe HTML even under `WithoutModelEvents`), and the never-trust-client rendering
rule are all covered in **[`documentation/rich-text.md`](rich-text.md)**.

> [!WARNING]
> Render rich HTML with `{!! !!}` **only** via `x-rich-text`, on already-sanitized data. Index
> cells use the escaped `x-rich-text-excerpt`. `Scene.contents` is the one carve-out — it stays
> Markdown-only (`ValidMarkdown` + `Str::markdown()`), never routed through the sanitizer or the
> editor.

## Static file export

**Admin → Export & import → Export** lets a signed-in user download one of their projects as a
`.zip`. The archive has exactly two top-level folders — **`data/`** (a lossless machine copy, the
source of truth for import) and **`book/`** (a human reading version of the manuscript).
The full on-disk contract is **[`documentation/export-format.md`](export-format.md)**; this section
is the architectural overview.

- **One service, HTTP-agnostic and async-ready.** `App\Services\StaticSiteExporter::export(Project,
  bool $includeMedia)` builds the whole archive and returns a finished temp-zip path — it takes no
  `Request` and returns no `Response`, so a future queued Job can reuse it unchanged. It reads media
  bytes with `Storage::disk('public')->get()` (never the `/storage` URL), so it needs no
  `php artisan storage:link` or any CLI step. The zip is built to `storage/app/exports`, streamed with
  `->deleteFileAfterSend(true)`, and the temp file is deleted on exception too, so a failed export
  leaks no partial zip.
- **The controller stays thin.** `ExportController@store` resolves the project, authorizes, delegates
  to the service, and streams the download. `ExportRequest` validates the form (`project_id`,
  `include_images`).
- **Two layers, one render boundary.** `data/` is **raw and lossless** — every field file holds the
  exact stored column value, never re-rendered or re-sanitized. `book/` is the **only** place the
  export renders Markdown to HTML — through the shared `Scene::renderedContents` accessor (the same
  render path the in-app views use), via Blade templates under `resources/views/exports/book/`
  rendered to string (HTML is never string-built in the service). Never blur the two.
- **The README's plain-text description** comes from `App\Support\RichText::toPlainText()`, the
  rich-text module's home for stripping stored HTML to prose — the exporter calls it rather than
  owning HTML-shape knowledge that has nothing to do with building a zip. Its sibling
  `RichText::toXhtmlFragment()` does the parallel job for the **EPUB** exporter: it normalises a
  sanitized rich-HTML `description` into well-formed XHTML (via `DOMDocument`) so an embedded act /
  chapter / scene description clears the epub's XML-well-formedness gate. Both keep HTML-shape
  knowledge in one place, beside the sanitizer.

> [!WARNING]
> **Export authorization is ownership, not just the admin gate.** The route sits behind `auth` +
> `can:access-admin` (any authenticated user), so `ExportController@store` **must also**
> `authorize('view', $project)`, mirrored in `ExportRequest::authorize()`. A foreign **or missing**
> `project_id` is a **403**, never a silent export of another user's project.

## Static site import

**Admin → Export & import → Import** reads an export `.zip` back into a **brand-new** `Project` owned
by the importing user. Import is a reconstruction from `data/` only — `book/` and `README.md` are
allowed to be present (real exports have them) but are **never read**. The on-disk contract it consumes
is the same **[`documentation/export-format.md`](export-format.md)** the exporter writes.

- **Untrusted input, validated before anything is written.** A `.zip` claiming to be an export is
  never trusted. `App\Services\Import\ArchiveValidator` is a six-check security gate — real zip,
  no zip-slip / absolute / drive-letter entry names, every entry inside the allow-listed arborescence
  (`App\Support\ImportRules`), a supported `data/manifest.json` `version`, every JSON descriptor's
  required keys, and every declared media file's **content-sniffed** type matching its declaration
  (a renamed `.php` masquerading as a `.png` fails here). `App\Services\Import\ContentSanitizer` then
  runs every `description.html` / `notes.html` / rendered `contents.md` through the app's existing
  rich-text allow-list, but **rejects the whole archive** on any violation rather than silently
  stripping it — deliberately stricter than a normal form save. Nothing reaches the disk or the
  database until both gates pass.
- **Ids are always remapped.** The archive's ids belong to the *exporting* installation; every new
  row gets a fresh id, and every reference (`event_id`, `plotline_ids`, attribute-value anchors, …) is
  resolved through an id map built during import. A reference that doesn't resolve is a validation
  failure, never a silently dropped relationship. `position` is replayed **verbatim** from the JSON,
  never re-derived from insertion order.
- **Anchors are reconciled, not duplicated.** Creating the `Project` fires `Project::booted()`, which
  seeds the main plotline and the Start/End bookend events. `ProjectGraphImporter` **updates those rows
  in place** with the archive's recorded fields and maps the archive's ids onto them — so the invariant
  (exactly one `is_main` plotline, exactly two `is_fixed` events) holds before and after import. A name
  collision only ever renames the *new* project (a timestamp suffix); it never blocks creation or merges
  into an existing project.
- **Checkpointed for resumability.** `App\Services\ProjectImporter` (`start()` / `run()` / `discard()`)
  ties the gate, the graph importer, and an `Import` tracking record together. Validation is **always**
  synchronous (so a bad upload is an immediate form error). The four graph phases — `project → timeline
  → story → codex` — each commit in their **own** DB transaction, checkpointing `phase` + the
  accumulated `id_maps` onto the `Import` row after each commit. A crash mid-import therefore leaves the
  row at its last completed phase with the uploaded zip + extraction kept on disk, so the user can
  **resume** (re-run only the remaining phases) or **discard** (roll back the partial `Project`, delete
  the working files, and remove the row) — never an orphaned half-import with no recovery path. See
  `.specs/shipped/2026-07/import/expanded/data-model.md` for the full checkpoint contract.
- **Synchronous by default, queued by opt-in.** `ImportSetting` (a singleton, same shape as
  `CrawlerSetting`) carries `max_archive_kilobytes` and `run_in_background`. With background mode off
  (the default, for installs with no queue worker) the whole import runs inline in the request and
  redirects to the finished project. With it on, `ImportController` dispatches `ProjectImportJob` and
  redirects with a "queued" status; only `run()` is ever deferred — validation still runs inline.
- **Two intentional authorization postures.** `POST admin.data.import` and
  `PATCH admin.data.import-settings` use the **any-authenticated-user** exception (like `CrawlerSetting`):
  there is no project yet to walk up to, so `ImportProjectRequest::authorize()` is simply
  `$this->user() !== null`. Once an `Import` row exists it has an owner, so `resume` / `destroy` go
  through a real `ImportPolicy` (`$user->id === $import->user_id`) — a non-owner gets a **403**. Do not
  collapse these two into one pattern.

> [!NOTE]
> The whole pipeline is covered end-to-end by `tests/Feature/ImportRoundTripTest.php`: it seeds a
> non-trivial project, exports it through the real `StaticSiteExporter`, imports the resulting zip
> through the real HTTP route with nothing mocked, and asserts the new project matches the source on
> every axis — plus a second import of the same zip proving disambiguation. The HTTP layer, service
> orchestration, and security gate also have their own focused suites (`ImportTest`,
> `tests/Unit/Import/*`).

## EPUB export (publication settings)

**Admin → Export & import → Export → Ebook** downloads a project as a standard `.epub`, built
by `App\Services\EpubExporter` on `rampmaster/phpepub`. Like `StaticSiteExporter` it is
HTTP-agnostic (takes a `Project`, returns a temp-file path, cleans up on exception), so a
queued job could reuse it. The library owns the mechanical package (OPF, nav, NCX, zip); the
service owns the content — every XHTML document (Blade under `resources/views/exports/epub/`,
never string-built), the CSS, the metadata values, and the navigation shape.

The whole export is parameterised by one lazily-resolved `PublicationSetting`
(`Project::publicationSettingOrDefault()` returns an *unsaved* default when the project has no
row). `addSections()` walks its `section_order` array and dispatches each key —
`title` / matter pages / `toc` / `body` / `appendix` — through a `match`.

Rules that bite if you don't know them:

- **`validatePackage()` is a hard gate inside every `export()`**: every shipped `.xhtml` must
  parse as XML and the OPF must validate against the vendored EPUB 3 RelaxNG schema. A failure
  is a generator bug (`RuntimeException`), not the user-facing `EpubExportException`.
- **Defaults must reproduce the pre-feature output byte-for-byte.** `EpubExporterTest`'s
  `defaults_v1_regression` guards this; every new gated feature ships off-by-default.
- **Scene bodies never render through `Scene::renderedContents`** — the exporter has its own
  SmartPunct converter, and the shared accessor must stay identical for the other consumers.
- **Rich HTML always goes through `RichText::toXhtmlFragment()`**; Markdown never does.
- **All formatting/ordering choices live on enums**, never a `match` on a raw string in the
  service or a view.
- **The library couples spine placement and nav entries**, which is why `Acts` depth renders
  one combined page per act rather than hiding chapter nav points.

> [!IMPORTANT]
> Before changing any of this, read **[`epub-export.md`](epub-export.md)** — the isolation
> rules, the nav-depth shapes, the chapter-cover and appendix gating, and why the regression
> guard normalises OPF timestamps instead of comparing raw bytes.

## Project search

Every project has a search page (`GET /projects/{project}/search`, named
`projects.search.index`, the last item in the primary nav) that scans the string/text fields
of six entities — Act, Chapter, Scene (contents + notes), Event, Plotline, and CodexEntry —
and renders the matches grouped into the same three sections as the menu (Timeline / Story /
Codex). Each section stacks one full-width result table per entity type — the same layout as
the entity list pages (an earlier 3-column grid proved too narrow for comfortable reading).
A table row is one matched **entity**: its name (linked to the edit page), the fields the
terms matched in (concatenated, e.g. "Name, Contents"), the highlighted text preview, and a
trailing view button (`x-icon-view-link`, same edit page — entities have no separate show
page). Entity types with no matches are hidden entirely (no empty-state table), and a
section whose tables are all empty is skipped — `SearchResults`' `has*Matches()` helpers
drive the per-section check so the Blade stays logic-free. It is
a plain `GET` form with a full-page reload — no AJAX;
`q` and `mode` round-trip via the query string, and an empty `q` is the normal landing state
(the form with no results), never a validation error.

- **The service.** `App\Services\ProjectSearch` is the first (and template) occupant of the
  `app/Services` layer: the controller stays thin (resolve → authorize → delegate → view) and
  all query logic lives in `search(Project, string $query, SearchMode): SearchResults`. The
  whole search is a **fixed six SELECTs** regardless of match count (one per entity type;
  Chapter/Scene scope to the project through `whereHas` on their parent chain), asserted by a
  query-count test. Each query fetches the entity's project-scoped rows, then **matching runs
  in PHP** (`entityMatches`) rather than in a SQL `WHERE` — see *Accent folding* for why.
- **Modes.** `App\Enums\SearchMode`: `AllTerms` (AND, the default), `AnyTerm` (OR),
  `ExactPhrase`. In AND mode a term may match in *any* searchable field of the entity — terms
  are not required to co-occur in one field. The mode only changes which entities `entityMatches`
  keeps; a kept entity always becomes **one result row listing every matching field**
  (a Scene matching in both `contents` and `notes` yields one row matched in
  "Contents, Notes").
- **Snippets.** `App\Support\SearchSnippet` builds a ~120-char context window around the first
  match and wraps matched terms in `<mark class="bg-sun-200">`, escape-then-highlight so raw
  HTML in scene text can never become live markup. Its output is the **only** `{!! !!}` on the
  page (rendered in `x-search.result-row`); everything else stays auto-escaped `{{ }}`. The
  row's preview is built from the **first** matching field, in the entity's declared field
  order — the "Matched in" column tells the reader where else the terms appeared.
- **Accent folding.** Matching is case- and accent-insensitive: `Melusine` finds `Mélusine` and
  the reverse. `App\Support\AccentFolder::fold()` (one 1:1 accent→base map) is the single source
  of truth, applied at **all three** match points that must agree — the entity gate
  (`ProjectSearch::entityMatches`), the per-field label check (`fieldContainsAnyTerm`), and
  `SearchSnippet`'s offset+highlight — so an entity kept by the gate always yields at least one
  field label and a correct snippet. Matching is a literal `str_contains` on folded plain text,
  which is why `%`/`_` in a query need no escaping (they are never wildcards) and rich-HTML
  fields are stripped to plain text before both matching and preview. Because the map is strictly
  one-character-to-one-character, folding preserves character offsets, which is how `SearchSnippet`
  matches on folded text but still highlights the *original accented* characters. Expanding
  ligatures (`ß`→`ss`, `æ`, `œ`) would break the offset invariant and are a documented non-goal.

> [!WARNING]
> Matching runs in **PHP, not SQL** — deliberately. A folding SQL expression is not portable:
> SQLite (the default/production driver) folds only ASCII case with no `unaccent`/ICU, and a
> per-column nested-`REPLACE` chain **overflows SQLite's parser on some builds** (it passed
> locally but failed CI). Folding in PHP against fetched rows keeps the behavior identical and
> driver-independent. Don't "optimize" the match back into a `WHERE`/`LIKE`/collation clause.

There is deliberately **no FULLTEXT index, no new package, and no pagination**: fetching a
project's rows and scanning them in PHP is fine at this project's scale (a full scan is already
what a leading-wildcard `LIKE` would cost) and is identical on every DB driver. Result caps and
a paginated per-domain page are a separate follow-up spec (`search_pagination`); verifying the
per-driver behavior is the `multiple-database-engines` spec's CI matrix.

## Navigation active state

The primary nav (`resources/views/layouts/navigation.blade.php`) highlights the section matching
the current route in **both** menus: the desktop dropdowns (Timeline / Codex / Story) — their items
and their collapsed trigger buttons — and the responsive (mobile) menu.

- **The component.** `x-dropdown-link` mirrors `x-nav-link` / `x-responsive-nav-link`: pass
  `:active` to get the light-panel highlight (`bg-aqua-50 text-navy-900 font-semibold`) plus
  `aria-current="page"` on the `<a>`. The prop defaults to `false`, so existing menus that don't
  pass it (the Settings dropdown) are visually unchanged. Active state is never colour-only — the
  `aria-current` is what tests assert on.
- **One source of truth for matching.** `App\Support\ProjectNavigation` is a per-request view
  model built by a view composer in `AppServiceProvider` and handed to the layout as
  `$navigation`. It answers both of the nav's questions: which `Project` the request belongs to
  (walking a shallow child route — `/scenes/{scene}/edit` — up its aggregate), and which section
  is active (`storyActive`, `plotlinesActive`, `toolsActive`, …). Per-codex-type highlighting is
  enum-aware: `codexTypeIsActive(CodexEntryType $type)`.
- **The menus are markup only.** `x-navigation.project-menu` (desktop) and
  `x-navigation.responsive-project-menu` (collapsed) both read the same `$navigation`, so they
  cannot drift. `x-navigation.dropdown-trigger` is the shared trigger button (label + chevron,
  `text-white border-flame-500` when active); `x-navigation.section-heading` is the collapsed
  menu's stand-in for a dropdown. A trigger is active when any of its children is.

- **The Configuration area works the same way.** `App\Support\AdminNavigation` supplies both the
  sidebar (`sections()`) and the Export & import subnav (`dataSubnav()`), also via a view
  composer, as `$adminNavigation`. Entries are `['label', 'href', 'active']`, ready for
  `x-sidebar-link`.
- **`x-sidebar-link` owns the state, the caller owns the geometry.** It renders the `<a>` with
  `aria-current` and the active/inactive colours for its `variant` (`sidebar` = accent border
  row, `tab` = underline). Padding, border width/side and layout come from the caller's `class`,
  because the Configuration sidebar, the revisions sidebar and the subnav are deliberately
  different sizes. Used by all three.

> [!NOTE]
> Adding a nav section is a change to `ProjectNavigation` plus a link in each menu component. Do
> **not** put `request()->routeIs(...)` back in the templates. This used to be two duplicated
> `@php` blocks in the layout, which is exactly how `$toolsActive` came to be defined in the
> desktop copy only and read by the responsive copy through PHP scope leak.

## Project picker

The bar's left block names the open project and switches between them —
`ProjectNavigation::otherProjects()`, rendered by both `layouts.navigation` menus.

- **Capped at five, ordered by name.** A shortcut, not an index: the "All projects" link is the
  complete list, so the cap costs a click and never a project. Both menus call the method, so it
  is memoized — otherwise every authenticated page pays for two identical queries.
- **The open project is absent from the desktop panel** (the trigger already names it) but
  present, marked active, in the responsive menu, which has no trigger.
- **The nav bar is full-bleed**, unlike `<main>`'s `max-w-7xl`: the logo and the picker anchor the
  left corner, the account menu the right. Above 1280px the bar deliberately does not line up
  with the content under it.

> [!WARNING]
> `x-dropdown`'s `width` prop maps only the legacy `'48'`; everything else is passed through as a
> raw class. `width="56"` silently renders a junk `56` class and an unsized panel — pass
> `width="w-56"`.

## Page title

`App\Support\PageTitle` renders the `<title>` of the authenticated layout: `"<project name> -
<app name>"` inside a project, the bare app name (`config('app.name')`, i.e. `APP_NAME`) outside
one. The project name leads because browser tabs truncate from the right, and the tab is the only
way to tell two open projects apart.

- The same view composer that builds `$navigation` also hands `layouts.app` a `$pageTitle`, built
  from `$navigation->project` — project resolution (including shallow child routes) stays in one
  place.
- `layouts.guest`, `layouts.public` and `welcome` show `config('app.name')` alone: no project is
  open, and a share link should not put the project's name in the visitor's tab.
- The app name is never a literal in a template — change it in `APP_NAME`.

## CSS build pipeline (Tailwind 4)

`@tailwindcss/vite` compiles the stylesheet directly inside Vite — there is no PostCSS config
and no `tailwind.config.js`; `resources/css/app.css`'s `@theme` block is the single source of
theme values, and Tailwind auto-detects classes by scanning the project instead of reading a
`content` array.

- **Theme tokens are runtime CSS custom properties** (`--color-ocean-500`, `--radius-sm`, …),
  not compile-time JS values. That is what lets `theme-switcher` (spec 2) override them per
  request instead of rebuilding the stylesheet.
- **Browser floor: Safari 16.4+ / Chrome 111+ / Firefox 128+** — v4 relies on `@property`,
  `color-mix()`, and cascade layers. Documented, not enforced at runtime: this is a self-hosted
  app with a small, known user base.
- A base-layer shim in `app.css` restores v3's default border colour; see that feature's
  `standing-issues.md` for why, and what removes it.

> [!WARNING]
> Scanning does not stop at templates — a utility class **named in Markdown prose** becomes a
> real rule in the stylesheet. `.specs/` and `documentation/` are therefore excluded via
> `@source not` in `app.css`. Add the same exclusion for any new folder that discusses class
> names, or the build ships utilities nothing renders.

## Where things live

| Concern | Location |
| --- | --- |
| Input validation | `app/Http/Requests` (Form Requests), `app/Rules` (reusable rules) |
| Authorization | `app/Policies/ProjectPolicy` |
| Domain invariants / lifecycle | Model `booted()` hooks |
| Reusable domain workflows | `app/Services` (e.g. `ProjectSearch`, `RevisionRecorder`, `RevisionPurger`), or an Action class |
| Constant / reference data | `app/Support` (e.g. `PlotlineColors`), `app/Enums` |
| Reusable UI | `resources/views/components` (Blade components) |
| Containerized dev/prod environment | `Dockerfile`(`.dev`), `docker-compose*.yml`, `docker/` — see [`documentation/docker.md`](docker.md) |
| Forced versions of transitive npm packages | `overrides` in `package.json` — see [`documentation/dependency-overrides.md`](dependency-overrides.md) |

## The rest of the documentation

This file is the map. Each feature with more to say than fits here has its own page:

| Page | What it covers |
| --- | --- |
| [`revisions.md`](revisions.md) | Autosave, save points, diffing, prune vs purge, revert/undo |
| [`codex.md`](codex.md) | The temporal step function, the three codex services, scene references |
| [`epub-export.md`](epub-export.md) | Publication settings, nav depth, the EPUB package gate |
| [`export-format.md`](export-format.md) | The static-archive file format — layouts, shapes, the `version` contract |
| [`rich-text.md`](rich-text.md) | The WYSIWYG field list, the sanitizer allow-list, the rendering rule |
| [`word-count.md`](word-count.md) | What counts as a word, the one stored column, totals without an N+1 |
| [`ui-components.md`](ui-components.md) | The Blade component catalogue — reuse one before writing a new one |
| [`best-practices.md`](best-practices.md) | How to write code here, including testing UI state |
| [`code-style.md`](code-style.md) | Formatting and naming conventions |
| [`glossary.md`](glossary.md) | The vocabulary — read this first if a term is unfamiliar |
| [`docker.md`](docker.md) | Running the project without a local PHP/Node setup |
| [`dependency-overrides.md`](dependency-overrides.md) | Why a transitive npm version is pinned |
