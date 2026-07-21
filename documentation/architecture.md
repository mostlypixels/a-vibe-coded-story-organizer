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

### One table, one controller, a type enum

All three entity kinds live in a single `codex_entries` table with a `type` column cast to
`App\Enums\CodexEntryType` (`Character` / `Location` / `Organization`). The columns are
identical across types, and the *type-specific* data is exactly what the flexible attribute
system handles — so one table stays DRY. A single `CodexEntryController` serves all three;
the type is a **route segment** (`{type}` ∈ `characters|locations|organizations`), resolved
via `CodexEntryType::fromRouteKey()`. The constraint, the nav links, and `fromRouteKey` all
derive from the enum — there are no hardcoded route-key string lists to keep in sync:

```php
// One grouped constraint, from CodexEntryType::routeKeys(); an unknown {type}
// 404s before the controller runs. Adding a fourth type needs no route edits.
Route::whereIn('type', CodexEntryType::routeKeys())->group(function () {
    Route::get('/projects/{project}/codex/{type}', [CodexEntryController::class, 'index'])
        ->name('projects.codex.index');
    // ...create, store...
});
```

The navigation dropdown (both the desktop and responsive menus) `@foreach`es
`CodexEntryType::cases()` instead of listing three literal links, and highlights the
**current** codex type rather than always the first link.

`edit` / `update` / `destroy` are flat (`/codex/{codexEntry}`) — the entry alone resolves them.

Around each entry hang: **aliases** (`codex_aliases`, sync-managed from a repeatable input),
flat **tags** (`tags` + `codex_entry_tag`, `firstOrCreate`d per project and `sync`ed), and
**media** (`codex_media`).

> [!NOTE]
> There is deliberately **no `cover_media_id` column**. The cover is simply the `codex_media`
> row whose `collection` is `Cover`, exposed via a `CodexEntry::cover()` `hasOne`. A FK would
> be a second source of truth *and* a circular reference (`codex_entries` → `codex_media` →
> `codex_entries`).

### Attribute definitions and the step function

An **attribute definition** (`codex_attributes`: e.g. "Hair color", "Frescoes") carries an
`applies_to` JSON array of `CodexEntryType` values deciding which sheets show it. Its
**values** (`codex_attribute_values`) are temporal: each row says *"from this event onward,
the value is X"* — a **start-anchored step function**. There is no stored end event; a period
runs from its `start_event`'s datetime until the next anchor (or the project's **End**), so
periods tile the timeline with **no holes or overlaps by construction**, and deleting a middle
anchor simply lets the previous value extend (which is why `start_event_id` can safely
`cascadeOnDelete`).

Resolving a value **at moment `t`** = the anchor whose datetime is the greatest `≤ t`.
Ordering is always the canonical `(event_datetime, events.id)` — never datetime alone —
because two events may share a datetime. When resolving *at an event*, an **anchor-identity
match wins first**: a scene "during Halloween" sees the Halloween value even if another event
shares its datetime.

"The project's **Start** / **End** event" — the sentinels every timeline resolves against —
has a **single definition**: `Project::startEvent()` / `Project::endEvent()` (the
earliest / latest `is_fixed` event, in canonical `(event_datetime, id)` order). Everything
that needs a bookend (`AttributeTimeline`, the entry controller) delegates to these methods
rather than re-running the query. Because the resolution is datetime-ordered, Start must stay
the **earliest** `is_fixed` event — but its date need not be frozen to guarantee that. Instead
the bookends form a **containment window**: `App\Rules\WithinEventWindow` (applied on every
event write — store/update and the Scene inline `new_event_datetime`) requires every non-fixed
event to satisfy `Start ≤ event_datetime ≤ End`, and forbids a bookend edit from swallowing an
existing event (Start can't pass the earliest regular event nor reach End; End the mirror).
Since `startEvent()`/`endEvent()` filter on `is_fixed`, regular events never compete for the
anchor, so the baseline can be neither deleted (undeletable events) **nor re-ordered** (nothing
sorts before Start) out from under the step function.

All of this lives in **`App\Services\AttributeTimeline`** (constructed for one entry+attribute
pair), not in the controller or a model hook:

- `valueAt(Event|Carbon)` — the resolution above (used by scene/event "as of" panels via the
  thin `CodexEntry::attributeValueAt()` wrapper).
- `ensureBaseline()` / `upsertAt()` / `removeAt()` — gap-free mutations. `upsertAt` is an
  **upsert** (`updateOrCreate` on the anchor), so the store endpoint has **no update route**:
  editing an existing period posts the same route with the row's anchor. **`upsertAt` enforces
  the baseline itself** — when the anchor isn't Start it calls `ensureBaseline()` first, so
  storing a mid-timeline period for a never-valued pair can't open a leading hole. The invariant
  therefore holds on *every* write path (period store, seeder), not only entry-create; a caller
  can't accidentally bypass it. `removeAt` refuses to delete the Start baseline while other
  values exist, returning a **`403`** (`abort_if`) rather than throwing a `RuntimeException`.

The store endpoint validates `value` as `['present', 'nullable', 'string', 'max:255']`, so an
**empty value is a first-class "recorded as blank"** — an empty baseline is savable and a value
can be cleared back to blank (`required` would forbid both). `nullable` is present because the
global `ConvertEmptyStringsToNull` middleware rewrites a blank input's `""` to `null`; the
controller casts it back with `(string)` before `upsertAt` (whose signature is `string $value`).
The timeline editor renders validation errors under `value` / `start_event_id` and re-fills
`old()` on failure, so a rejected save no longer silently does nothing.

> [!IMPORTANT]
> **Invariant — leading anchor at Start.** Every (entry, attribute) with any value has exactly
> one value anchored at the project's *Start* event, so `valueAt(t)` is **total** for
> `t ≥ Start` and callers never handle "no value". The Start/End events are `is_fixed` and
> undeletable, and the containment window keeps Start earliest, so the anchor can be neither
> orphaned nor re-ordered.
> `upsertAt` enforces this on every write (not just entry-create). This invariant lives in
> `AttributeTimeline` (a service the seeder can call directly), **not** a `booted()` hook —
> hooks are suppressed under `WithoutModelEvents`.

`App\Services\CodexMediaService` is the second service: it owns the storage path/naming, the
single-cover rule (replace the existing `Cover` row + its file), position assignment, and —
critically — **deleting files off disk** on every removal path. `CodexEntry`'s `deleting` hook
calls `purge()` *before* the FK cascade drops the rows, because `cascadeOnDelete` removes the
DB rows but never the files.

> [!WARNING]
> **A DB cascade bypasses model hooks — so it bypasses file cleanup.** Deleting a *project*
> (or a *user account*) cascades `project → codex_entries → codex_media` entirely at the
> database level; `CodexEntry`'s `deleting` hook never fires, so on its own it would leak
> every media file on disk. Two hooks close this: `Project::deleting` calls
> `CodexMediaService::purgeProject()` (one query for the paths, delete the files, let the
> cascade drop the rows), and `User::deleting` Eloquent-deletes its projects
> (`$user->projects->each->delete()`) so the `Project` hook fires per project. That keeps
> `purgeProject()` the **single** purge trigger for a project's files.

The entry save flow (`CodexEntryController@store` / `@update`) keeps **disk I/O outside the
`DB::transaction`**. The transaction does DB-only work and *returns the paths* of the media
rows it removed; only after it commits does the controller delete those files
(`CodexMediaService::deleteFiles`) and write the new uploads (`storeMediaUploads`, which
unlinks a just-written file if its row insert throws).

> [!WARNING]
> **Why post-commit, and the trade-off.** Doing disk deletes/writes *inside* the transaction
> is unsafe both ways: a rollback after a file delete leaves a surviving row pointing at a
> missing file (404), and files written before a later failure survive the rollback as
> orphans. Acting after commit fixes both — at the cost that a post-commit **upload** failure
> yields a *saved entry with fewer media than requested* plus a 500. That is deliberately
> accepted: a saved entry with one missing image beats a rolled-back edit with corrupted
> disk state.

### Scene references (`SceneReferenceMatcher`)

`App\Services\SceneReferenceMatcher` is the third Codex service. It owns the whole-word,
**case-sensitive**, Unicode-aware rule that decides which codex entries a scene's `contents`
mention, and persists the result in the derived `scene_codex_entry` pivot (see the data-model
doc). A term is an entry's `name` **or** any of its aliases (aliases shorter than 3 characters
are excluded — a false-positive guard; `name` has no floor). Matching runs on the raw Markdown
`contents`, never `description`/`notes`, and never on rendered HTML.

- `syncScene(Scene $scene)` recomputes one scene's links; `syncProject(Project $project)`
  recomputes every scene's, reusing one per-project regex it builds once.
- **Every call is a full `sync()`** for its scope — never an incremental attach/detach. This is
  the invariant that keeps the pivot from ever drifting from "what should match": there is no
  code path that adds or removes a single row. A stale row is always dropped on the next sync.
- Both sides are normalized to Unicode **NFC** (`ext-intl`'s `Normalizer`) before matching so
  visually-identical accented text (French/Italian names) from different input sources compares
  byte-equal. Malformed UTF-8 in a scene's `contents` is caught, logged via `Log::warning`, and
  degrades that scene to "no references" — it never throws and never blocks the scene's save.
- Hyphens are part of the word: "Jean" does not match inside "Jean-Luc". The boundary lookaround
  includes `-` alongside `\p{L}\p{N}`, and there is deliberately **no `i` flag** (a character
  named "Luck" must not match the common noun "luck").

> [!IMPORTANT]
> **It is a service, not a `booted()` hook** — for the same reason as `AttributeTimeline`. The
> codex-entry update path only rescans when the alias set or `name` actually changed (a
> before/after comparison a hook cannot express), the project-wide rescan touches records well
> beyond the model being saved, and a service can be called directly by a seeder or the importer
> without `WithoutModelEvents` silently suppressing it. Do **not** move this into a model hook.

> [!NOTE]
> This is **not** the codex index page's name-or-alias search. `CodexEntryController::index` does
> a case-insensitive SQL `LIKE` substring match to help a writer *find* an entry;
> `SceneReferenceMatcher` answers a different question (does this exact term appear as a whole,
> case-sensitive word in this prose). Keep the two separate — their semantics differ on purpose.

Normal editing never needs a manual resync — scene and codex entry saves call `syncScene`/
`syncProject` themselves. Two escape hatches exist for everything else: the
`codex:sync-references {project?}` artisan command (every project, or one by id) and, per
project, a **"Resync codex references"** footer form on the project edit page
(`ProjectController::syncCodexReferences`, `update` authorization) — its own form, separate from
the main project-fields form, since it isn't part of that resource's own data. Both call
`syncProject()` and exist to backfill scenes that predate this feature or recover from a
suspected drift.

### Seeding caveat

Like acts/chapters and the main plotline, the Codex is subject to `WithoutModelEvents`:
`MelusineSeeder` sets `position` explicitly on `codex_attributes`, and seeds temporal values
by calling `AttributeTimeline::ensureBaseline` / `upsertAt` **directly** rather than relying on
any hook. It seeds the hair-color story (Mélusine: raven black → silver on Saturdays after the
curse → wild once she transforms) end to end.

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

**Admin → Export & import → Export → Ebook** lets a signed-in owner download one of their
projects as a standard `.epub`, built by `App\Services\EpubExporter` on top of the
`rampmaster/phpepub` library. Like `StaticSiteExporter` it is **HTTP-agnostic** (takes a
`Project`, returns a finished temp-file path, cleans up on exception) so a future queued job
could reuse it. The library owns the mechanical package (mimetype, `container.xml`, the OPF
metadata/manifest/spine, the EPUB 3 nav document, the NCX, the zip); this service owns the
**content** — every XHTML document (Blade under `resources/views/exports/epub/`, never
string-built in PHP), the CSS, the metadata *values*, and the navigation shape.

Two isolation rules are load-bearing and must not be "simplified" away:

- **Scene bodies and the four front-/back-matter Markdown pages render through the service's
  own private SmartPunct `CommonMarkConverter`** (smart dashes/ellipses/quotes), *never*
  `Scene::renderedContents` — that accessor is the shared render path for the Story overview,
  the share page, and the `book/` export, and must stay byte-for-byte identical.
- **Rich-HTML fields (act/chapter/scene `description`, codex entry `description`) go through
  one shared `App\Support\RichText::toXhtmlFragment()`** (`DOMDocument` load-HTML → save the
  body fragment as XML) so a sanitized-but-not-necessarily-XHTML fragment — an unclosed `<p>`,
  a bare `<br>` — becomes well-formed XHTML. Embedding such a fragment raw would fail the
  package's hard XML gate (below). Markdown fields never touch this helper; rich-HTML fields
  never touch the Markdown converter.

> [!IMPORTANT]
> **`validatePackage()` is a hard gate, run inside every `export()`.** Every shipped `.xhtml`
> (this service's pages *and* the library's nav/cover pages) must parse with
> `DOMDocument::loadXML()`, and the OPF must validate against the vendored EPUB 3 RelaxNG
> schema (`resources/epub-schemas/`, no JVM/epubcheck at runtime). A failure there is a
> **generator bug** and throws a plain `RuntimeException` (let it 500 and be logged) — it is
> *not* `EpubExportException`, which is reserved for the one user-facing case: a project whose
> skip-empty `filteredTree()` came back empty (nothing to export).

### `PublicationSetting` drives everything

The whole export is parameterised by one lazily-resolved `PublicationSetting`
(`Project::publicationSettingOrDefault()` — an **unsaved default** instance when the project
has no row; see the *Publication settings* model note). `export()` resolves it once and threads
it through every private method. All formatting/ordering choices live on **enums**
(`ChapterTitleFormat::format()`, `DividerType::dividerHtml()`,
`TableOfContentsDepth::includesChapters()/includesScenes()`), never a `match` on a raw string
in the service or a Blade view — `ChapterTitleFormat::format()` in particular is the single
source of truth shared by the chapter page heading *and* its nav/TOC label, so the two can
never drift.

> [!IMPORTANT]
> **Defaults reproduce the pre-feature output byte-for-byte.** Every metadata/cover toggle
> defaults `true`; every *new* rendering (scene titles, all descriptions, front/back matter,
> chapter covers, the codex appendix) defaults **off**. `EpubExporterTest`'s
> `defaults_v1_regression` guard exports the same project twice — once on the lazy default
> (no row) and once on an explicit `PublicationSetting::factory()` row — and asserts the two
> `.epub`s are **content-identical**. Every later exporter change must keep it green; a new
> gated feature that is off-by-default cannot alter a default project's output.

> [!WARNING]
> **The guard normalises the OPF timestamps; it does not compare raw `.epub` bytes.** The
> library stamps `dc:date` / `dcterms:modified` from `time()` at finalize, so two back-to-back
> exports that straddle a wall-clock second differ by those two lines even though every content
> document is identical — under the parallel test runner that raced into an intermittent
> failure. The guard therefore unzips both packages and compares **entry-by-entry**,
> normalising only those two `time()`-derived OPF lines (`stripOpfTimestamps()`). Do not
> "restore" it to a single raw-byte `assertSame` — that is the latent flake, not a stricter
> check.

### The `section_order` walk

`addSections()` replaces what was once a hard-coded `title → toc → body` sequence: it walks
`PublicationSetting::section_order` (an ordered JSON array of component keys — `title` pinned
first by the model, `toc`/`body`/the matter sections movable via the config form's
move-up/move-down idiom) and dispatches each key through a `match`:

| Key | Method | Notes |
| --- | --- | --- |
| `title` | `addTitleSection()` | The story title page. Always first (the model pins it). |
| `dedication`, `acknowledgements`, `preface`, `postface` | `addMatterSection()` | Markdown pages, one shared `matter.blade.php` view driven by `MATTER_SECTIONS`. |
| `toc` | `addTocSection()` | The in-book TOC page (a real spine page, distinct from the reader-chrome nav). |
| `body` | `addBody()` | The act/chapter/scene tree — the manuscript itself. |
| `appendix` | `addAppendixSection()` | The optional codex appendix (below). |

Each front-/back-matter section renders **only when its `include_*` toggle is on AND the
Project's Markdown column is non-empty** — a disabled toggle *or* an empty field renders
nothing, and this "enabled AND has content" rule extends to the appendix. A toggle gates a
section on/off **independently** of its position in the order.

### TOC/nav depth (`table_of_contents_depth`)

The depth setting changes both the in-book TOC page (`renderToc()`) and the library nav that
`addBody()` builds, in lockstep:

- **`Chapters` (default)** — the two-level Act → Chapter tree. Each act is its own divider
  page (a root nav entry); each chapter a nested page beneath it.
- **`Scenes`** — adds a *third* nav level. Each chapter page emits `id="scene-{id}"` anchors
  (`chapter-body.blade.php`, gated on this depth **only**, so the default depth stays
  byte-for-byte as before), and the nav hangs a per-scene entry under the chapter whose href is
  `chapter-{id}.xhtml#scene-{id}` — added with **null content**, which the library registers as
  an in-page anchor entry *without* a new spine page.
- **`Acts`** — the nav lists **only acts**. This one is not a simple "skip the chapter nav
  points" branch, and the reason is a hard library constraint:

> [!WARNING]
> **`rampmaster/phpepub` couples spine placement and nav entries — there is no
> spine-without-nav API.** Every content-bearing `addChapter()` adds a manifest item *and* a
> spine `itemref` *and* a nav point together; `NavPoint::setNavHidden(true)` is honoured by the
> NCX but **not** by the EPUB 3 nav document (confirmed by a standalone spike). So you cannot
> put a chapter page in the reading order at `Acts` depth while keeping it out of the nav.
> `Acts` depth therefore renders **one combined spine page per act**
> (`renderActWithChapters()` → `act-combined.blade.php`): the act divider plus all its chapters
> in a single `act-{id}.xhtml` document (each chapter still its own `<section>` for the
> page-break CSS). One `addChapter()` per act ⇒ exactly one nav entry per act, and **no
> standalone `chapter-{id}.xhtml` files are packaged** at this depth. The standalone-page shape
> is impossible here without shipping an unreadable book (chapters in neither spine nor nav) or
> fragile regex-surgery on the finalized nav.

`act-combined.blade.php` and the standalone `act`/`chapter` pages share their inner bodies via
`partials/act-body.blade.php` / `partials/chapter-body.blade.php` (and the exporter's
`actViewData()` / `chapterViewData()` helpers), so the two rendering paths cannot drift.

### Chapter cover pages

`addChapterCoverPage()` inserts a full-page cover image before a chapter, gated by
`include_chapter_covers` **and** a `cover_image` set on the chapter **and** the file still
existing on the `public` disk (a missing file is skipped silently — the export never fails,
mirroring the project cover). It is a **nav sibling** of the chapter (same level under the act,
immediately before the chapter's own entry), *not* a child, so it never disturbs the `Scenes`
depth's own nested scene anchors. At `Acts` depth — where there is no standalone chapter page —
each cover page is a root-level nav entry immediately before the combined act page that holds
that chapter. The image bytes go through the library's generic `addFile()` (manifest-only),
namespaced `images/chapter-cover-{id}-{basename}`, never `setCoverImage()` (that one-shot API is
reserved for the single package-level project cover in `applyCover()`).

### The codex appendix

`addAppendixSection()` fills the reserved `appendix` slot: an optional back-matter appendix of
codex entries — a heading page (a root nav entry) plus one page per entry nested beneath it
(entry name + `description` run through `RichText::toXhtmlFragment()`). It is a **true no-op**
unless all three hold: `include_codex_appendix` is on, at least one `appendix_entry_types` is
selected, **and** the project actually has entries of those types (a lone heading with no
entries is pointless). Entries load filtered to the selected types, ordered `(type, name)`.

When `appendix_include_images` is on, each entry's `media` relation is eager-loaded (ordered
`(collection, position)`, matching the archive) and `addAppendixEntryImage()` embeds the
entry's **first image only** — deliberately the first media row that is *both* an `image/*` MIME
type *and* has bytes on disk, so a metadata-only imported row (null path) or a non-image
reference file (a PDF) is skipped over rather than embedded as a broken `<img>`. A missing file
returns null and the entry page renders text-only. Embedding all images, and a `Review` entity,
are explicit V2 non-goals. When the images toggle is off, `media` is never loaded — no wasted
query.

### Publication settings (the model)

`App\Models\PublicationSetting` is **one lazy row per project**: never auto-created in
`booted()`, no backfill migration. `Project::publicationSettingOrDefault()` returns an unsaved
instance whose field defaults match `PublicationSettingFactory::definition()` field-for-field
(the two are the reference the defaults===v1 guard leans on — keep them in sync). The config
form is `PublicationSettingController` + `UpdatePublicationSettingRequest`, whose shared
`configRules()` static method is the single rule set used by **both** the form's `rules()` and
the archive importer's untrusted-config validation (see *Static site import* / the export-format
doc), so the form and the import path can never validate differently. Authorization is the
ordinary `ProjectPolicy` walk (`update` to write, `view` to read/export) — no new policy.
`SECTION_KEYS` / `PINNED_FIRST_SECTION` and the `moveSectionUp/Down` helpers keep the sortable
section order's membership and pinning rules in exactly one place.

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
- **One source of truth for matching.** All the route-match booleans (`$storyActive`,
  `$plotlinesActive`, `$codexActive`, …) live in a single `@php` block at the top of the
  `@if ($project = …)` guard, and are reused by the desktop triggers, the desktop dropdown items,
  **and** the responsive menu. A trigger is active when any of its child booleans is (using
  `x-nav-link`'s look, `text-white border-flame-500`). Per-codex-type highlighting is enum-aware and
  computed inline in the `CodexEntryType::cases()` loop. There is deliberately **no** `Nav` support
  class or view composer — that would be new architecture for a pure styling tweak.

> [!NOTE]
> When you add a new nav section, add its matcher to that `@php` block (the desktop and responsive
> copies stay identical) and reference the named boolean — do **not** scatter fresh inline
> `request()->routeIs(...)` calls through the template. Reuse the exact `routeIs(...)` globs the
> menu already uses so exactly one section is active at a time.

## Where things live

| Concern | Location |
| --- | --- |
| Input validation | `app/Http/Requests` (Form Requests), `app/Rules` (reusable rules) |
| Authorization | `app/Policies/ProjectPolicy` |
| Domain invariants / lifecycle | Model `booted()` hooks |
| Reusable domain workflows | `app/Services` (e.g. `ProjectSearch`), or an Action class |
| Constant / reference data | `app/Support` (e.g. `PlotlineColors`), `app/Enums` |
| Reusable UI | `resources/views/components` (Blade components) |
| Containerized dev/prod environment | `Dockerfile`(`.dev`), `docker-compose*.yml`, `docker/` — see [`documentation/docker.md`](docker.md) |
| Forced versions of transitive npm packages | `overrides` in `package.json` — see [`documentation/dependency-overrides.md`](dependency-overrides.md) |
