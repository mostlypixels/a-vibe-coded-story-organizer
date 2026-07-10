# Export to static files — Plan overview

> [!IMPORTANT]
> **This plan supersedes the pre-grill `expanded/` docs.** The design changed
> substantially during grilling: the export is now a **two-layer** artifact (a
> human-readable `book/` reading version **and** a lossless machine `data/` layer built to
> be **reimportable into this same app later**). The `expanded/overview.md` "non-goal:
> import/round-trip" and its single-tree/`storyline.html` layout are **obsolete**. Trust
> *this* file and the task files; consult `expanded/data-model.md` only for still-true
> schema facts (image ownership lives on `CodexEntry`; `ext-zip` is required) and
> `expanded/architecture.md` only for the where-logic-lives mapping.

## What we're building

From **Admin → Export & import → Export**, a signed-in user picks one of their projects,
chooses whether to include images/files, and downloads a `.zip` containing **two folders**:

- **`book/`** — a human reading version: a TOC `index.html`, act folders, one compiled
  HTML file per chapter (scene prose joined by `<hr>`, prev/next navigation).
- **`data/`** — a **lossless** structured export (JSON + raw field files + media) that a
  future *import* feature will use to reconstruct the project exactly. Import itself is
  **not** in this feature; we only produce an export *capable* of it.

Everything happens through the web UI — **no command-line step** for the user.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-endpoint-and-form` | `ext-zip` dep, POST export route, `ExportController@store`, `ExportRequest`, the Export form (project selector + media toggle + empty state), and the HTTP-agnostic `StaticSiteExporter` skeleton that already emits `data/manifest.json` and streams a real zip. End-to-end verifiable slice. |
| 02 | `02-data-story` | `data/` Story branch: project + acts + chapters + scenes as `<id>-slug` entity dirs with per-entity JSON + raw field files + scene relationships. |
| 03 | `03-data-timeline` | `data/` Timeline branch: plotlines + events (incl. bookends/main plotline) with their relationships. |
| 04 | `04-data-codex` | `data/` Codex branch: entries (aliases, tags, **attribute values anchored to events**, media), attribute definitions, tags, and the media-bytes toggle. |
| 05 | `05-book-layer` | `book/` TOC + compiled chapter pages + prev/next + minimal CSS; finalize CHANGELOG. |

Tasks 02–05 each depend only on **01** (they add an independent branch/method to the
exporter and assert their own zip entries). Do them in order; 05 may reuse the
act→chapter→scene loading introduced in 02.

## Binding design defaults (do not re-litigate)

- **Two folders**: `book/` (human) and `data/` (machine, source of truth for future
  reimport). The zip's top level contains exactly these two.
- **`data/` layout** — grouped by type, every entity is a **directory** named
  `<db-id>-slug`, containing a `*.json` (scalars + stable DB id + relationship **ID lists**
  + links to its field files) plus **raw** field files:
  - `contents.md` = raw Markdown (exact column value), `description.html` / `notes.html` =
    the **stored sanitized HTML fragment** (exact column value, **no** `<!doctype>` wrapper,
    no re-rendering, no re-sanitizing).
  - Nesting mirrors ownership: `data/acts/<id>-slug/chapters/<id>-slug/scenes/<id>-slug/`.
  - Timeline: `data/timeline/plotlines/<id>-slug/`, `data/timeline/events/<id>-slug/`.
  - Codex: `data/codex/<type>/<id>-slug/` (+ co-located media); plus flat
    `data/codex/attributes.json` (definitions) and `data/tags.json` (no rich fields → no
    directories).
  - Root: `data/project/` (`project.json` + `description.html`) and `data/manifest.json`
    (`version: 1`, project id, ISO export timestamp, `includes_media` bool).
- **`book/` layout** — `book/index.html` (TOC: each act title with its chapter titles as
  links); `book/NN/` act folders (zero-padded act **position**); `book/NN/NN.html` chapters
  (zero-padded, **per-act** chapter position). Each chapter page = chapter title + each
  scene's `contents` rendered via `Str::markdown()` joined by `<hr>` (no scene titles) +
  **prev/next** chapter links top & bottom. Prev/next follow global reading order **crossing
  act boundaries**; the first chapter's prev and last chapter's next link to `index.html`.
  Minimal inline CSS only.
- **Stable IDs = database primary keys.** Written into every JSON; all cross-references
  (`event_id`, `chapter_id`, `attribute_id`, `start_event_id`, `plotline_ids`, `tag_ids`,
  `mentioned_event_ids`) use those ids. Directory-name slugs are cosmetic.
- **Media toggle** — labelled **"Include images & files"**; governs whether *any*
  `codex_media` **bytes** are copied (covers `cover`, `reference_image`, `reference_file`).
  The `media[]` **metadata** in `entry.json` is written **regardless** of the toggle.
- **Excluded from export**: scene `share_token` / `share_expires_at` (deployment secrets).
- **Zip download name**: `Str::slug($project->name) . '-' . now()->format('Ymd-His') . '.zip'`.
- **`ext-zip`** added to `composer.json` `require`.

## Core invariants every task must preserve

1. **Authorization is ownership, not just the admin gate.** The route sits behind `auth` +
   `can:access-admin` (any authenticated user), so `ExportController@store` **must also**
   `authorize('view', $project)`, mirrored in `ExportRequest::authorize()`. A foreign or
   missing `project_id` → **403** (never a silent export of another user's project). Cover
   the 403 in every task that can reach the endpoint.
2. **Position ordering.** Acts/chapters/scenes/attributes are emitted in `position` order
   (the app-wide invariant); this drives both `book/` numbering and `data/` iteration.
3. **`data/` is lossless & raw.** Field files carry the **exact stored column value** —
   never re-rendered, re-sanitized, or reformatted. Only the `book/` layer renders
   (`Str::markdown()` on `contents`). Do not blur the two.
4. **Stable-id integrity.** Every relationship is expressed as DB ids so a future import can
   remap them; never rely on slugs/filenames for identity.
5. **The exporter is HTTP-agnostic and async-ready.** `StaticSiteExporter` takes a `Project`
   + options and returns a finished zip path on a disk — no `Request`/`Response`
   dependency — so a future queued Job can reuse it unchanged. It reads media bytes with
   `Storage::disk('public')->get($path)` (**not** the public `/storage` URL), so it never
   depends on `php artisan storage:link` or any CLI step.
6. **Temp-file hygiene.** The zip is built to a temp/storage path, streamed with
   `->deleteFileAfterSend(true)`, and cleaned up on exception too — no orphaned zips.

## Testing posture

Every task ships feature tests in the `tests/Feature/ProjectTest.php` style (plain PHPUnit,
`RefreshDatabase`, factories, `actingAs`, `route()`), run via `composer test` on in-memory
SQLite (`ext-zip` must be enabled in the runner). Tests capture the response bytes into a
temp file, open it with `ZipArchive`, and assert on entry names + `getFromName()` contents.
Always include the **403 non-owner** case and the **empty-project** case where reachable.

## Documentation

Create `documentation/export-format.md` (the machine `data/` format contract — the future
import's spec) in task 01 and append each branch's section as it lands. Update
`documentation/architecture.md` with a *Static file export* section (two layers, the
service, the authorization exception, async-readiness). Add one `Added` entry under
`## [Unreleased]` in `CHANGELOG.md` (finalized in task 05).
