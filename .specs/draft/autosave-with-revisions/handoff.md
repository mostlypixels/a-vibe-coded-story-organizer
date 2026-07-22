# Handoff — Autosave With Revisions

Decisions resolved by grilling on 2026-07-21/22, ahead of `/mp-spec-expander`.
Companion to `spec.md`, which stays the hand-written source of intent. Where the two
disagree, **this file wins** — it records where the spec was ambiguous, incomplete, or
wrong once checked against the code.

> [!IMPORTANT]
> **Blocked as of 2026-07-22.** §9 is resolved, but grilling surfaced a lossy-Markdown
> problem (§11.4) that belongs to the editor, not to autosave. A **TipTap editor
> capability spec is now a hard prerequisite** — see §10. Do not run
> `/mp-spec-expander` on this folder until that spec answers what the editor can and
> cannot round-trip.

Each decision below states what was chosen, why, and what was rejected. The rejected
options are kept deliberately: they are the questions a reviewer will ask, and the
answers are already here.

---

## Codebase constraints this design had to fit

Established by reading the code, not assumed:

* **Stack** — Laravel 13 / PHP 8.5, Blade + Alpine 3 + axios (already global in
  `resources/js/bootstrap.js`), TipTap 3 for rich text, Tailwind. Queue default is
  `database`, with a `ProjectImportJob` precedent.
* **Two kinds of long text already exist.** `App\Support\RichTextFields` owns the
  sanitized-HTML list (HTMLPurifier via `SanitizeHtml`); `Scene.contents` is
  deliberately Markdown-only (`ValidMarkdown` + `Str::markdown()`) and never routed
  through the sanitizer. Any registry this feature adds must respect that split.
* **Projects are single-owner.** `ProjectPolicy` is `$user->id === $project->user_id`
  with no collaborator concept. The spec's "revisions save the user id" is therefore
  future-proofing, not a present need — but it costs nothing and stays.
* **Saving today** is a full-form POST → Form Request → `$model->update(...)`, and for
  scenes → `SceneReferenceMatcher::syncScene()` on every save.
* **Deletes cascade at the database level.** `chapters`/`scenes` use
  `constrained()->cascadeOnDelete()`, so deleting a Project removes acts → chapters →
  scenes **without firing Eloquent events**. Any cleanup relying on model hooks is
  broken by construction.
* **Portability is a live constraint.** `.specs/draft/multiple-database-engines` commits
  the project to sqlite/mysql/mariadb/pgsql/sqlsrv with portable Blueprint DDL — no
  engine-specific partitioning, no FULLTEXT, and window functions are a claim that would
  need verifying rather than a free move.

---

## 1. Storage

### 1.1 One polymorphic `revisions` table

`revisions(id, revisionable_type, revisionable_id, field, value, project_id, user_id,
label, origin, created_at)` with a composite index on
`(revisionable_type, revisionable_id, field, created_at)`.

**Why.** Table *count* is not a performance lever — splitting into `scene_revisions`,
`act_revisions`, … partitions the same rows while multiplying migrations, models,
policies and tests, and the spec explicitly requires that "future fields must implement
the same functionality" (a per-model design taxes every future field with a migration).

The levers that actually matter, measured against a 3,000-word scene (~18 KB):

| Lever | Effect |
|---|---|
| Coalescing (§2.2) | A 2-hour session at a 2s debounce is ~1,000 inserts ≈ **18 MB**; coalesced to 60s buckets it is ~120 rows ≈ **2 MB** |
| Not selecting `value` in list queries | History page reads ~200 B/row instead of ~18 KB/row |
| Pruning (§4) | Bounds the table over time |
| Separate tables per model | ~0× |

**Rejected:** a separate `revision_payloads` side table now (buys insurance against a
sloppy query we can prevent with a review rule, at the cost of a join on every read and
a second write on every save); per-model tables (6× surface, no gain).

**Documented future lever, not built:** splitting the `value` *column* into a side table
if the history list is ever measured slow. Also deliberately **not** built: reverse
deltas and gzip compression — each is a 5–10× storage win and each adds a failure mode
to a feature the spec calls "critical, no margin for error".

### 1.2 `origin` enum, not a pile of booleans

`App\Enums\RevisionOrigin`: `automatic | manual | revert | import | baseline`.

**Only `automatic` is ever prunable.** This single column replaces what would otherwise
have become `is_manual` + `is_imported` + `is_baseline` + `is_revert`, and states the
retention rule in one place. Matches CLAUDE.md's enums-over-magic-values guidance.

`label` is a **plain nullable string** on the revision — the revision's name. It is
explicitly **not** the existing `Tag` model, which is a project-scoped *story*
vocabulary joined to `CodexEntry` ("villains", "magic"). Mixing editorial metadata into
that vocabulary was rejected. The spec's "name" and "tag" are one concept: search the
history list by `label` with the same portable `LIKE` pattern `ProjectSearch` uses.

A revision is exempt from age-pruning when `origin !== automatic` **or**
`label IS NOT NULL`.

### 1.3 `project_id` is denormalized onto every revision

With a real `constrained()->cascadeOnDelete()`.

**Why.** Polymorphic relations have no FK cascade, and (see constraints above) deleting a
project wipes its scenes at the DB level with no Eloquent events — a `deleting` hook
would silently never run and orphans would accumulate invisibly. The FK kills the bulk
case at the database level; per-entity deletes are swept by the daily prune via a
portable `whereNotExists` per type. `project_id` also gives the zip export and the
storage panel the index they need.

For `Project` itself, `project_id` is its own id.

---

## 2. Saving

### 2.1 Autosave writes the live row *and* a revision

Autosave PATCHes the real column exactly as the form does today, and records a revision.
There is **no draft-vs-published split**: exports, search, public share links and
`SceneReferenceMatcher` keep seeing one truth.

**Rejected:** draft-only-until-manual-save — every read path would have to decide which
value it shows, roughly doubling the build and the test surface.

### 2.2 Significance = a coalescing window, per field

**The live column is written on every autosave, unconditionally.** That is the data-safety
guarantee. Only the *history* is coarsened.

Within a coalescing window, the open `automatic` revision for that
`(entity, field, user)` is **overwritten in place**; when the window expires, the next
save inserts a new row. A save whose value is byte-identical to the last revision writes
no revision at all (so typing something and undoing it leaves no trace).

Windows live in `config/revisions.php`, keyed by `Model.field` with a default:

| Field | Window |
|---|---|
| `Scene.contents` | **60 seconds** |
| everything else | **5 minutes** (default) |

Sizing for the 60s choice: ~60 rows/hour ≈ 1 MB/hour on one scene; a 4-hour writing day
≈ 4 MB. Fine enough to never lose more than a minute of shape, coarse enough that the
history page stays readable.

**Rejected:** a character-delta floor (introduces a threshold nobody can justify, and
losing a deliberate one-word change is exactly the failure the spec forbids); magnitude
without a time window (unbounded row rate, and a real edit distance over 20 KB every 2
seconds is too expensive inline); a user-facing knob (writers can't reason about it and
it makes retention vary per install).

### 2.3 Autosave covers long-text fields only

Short fields and relations (`name`, `chapter_id`, `status`, `event_id`,
`mentioned_events`) keep the existing Form Request flow, because they carry cross-field
rules that do not survive field-level saves — `chapter_id` must belong to the project,
the event must sit inside `[Start, End]` (`WithinEventWindow`), `mentioned_events` is a
`sync()`.

**Known gap, accepted deliberately:** prose saves itself, the name does not. Closing it
is the job of the sibling `.specs/draft/data-loss-warnings` spec (a dirty-form guard).
**This is a hard dependency and must be stated in the expanded spec**, not left implicit.

### 2.4 Triggers

| Trigger | Writes value | Cuts/updates revision | Runs `SceneReferenceMatcher` | Closes window |
|---|---|---|---|---|
| Debounce (typing stops) | yes | yes (coalesced) | **no** | no |
| Blur | yes | yes | yes | no |
| **Ctrl-S** | yes | yes | yes | **yes** |
| Form submit (Save) | yes | yes, `origin: manual` | yes | yes |

**Ctrl-S is a flush + window close, not a permanent checkpoint.** Writers press it
reflexively (Word muscle memory) dozens of times a session; flagging each one `manual`
would make every reflex an immortal row that only the bulk purge can remove. Closing the
window still gives the perceptible "this moment is preserved" behaviour, because the next
edit starts a fresh revision.

Only the form's Save button (`origin: manual`) and an explicit label make a revision
permanent.

### 2.5 Codex reference matching moves to coarse triggers

`syncScene()` loads every codex entry + alias in the project, builds a combined regex,
matches it over the contents, then does a pivot `sync()`. Running that every 2 seconds
would add real latency to the save the user is watching.

Debounced autosaves therefore **skip the matcher**; it runs inline on blur, Ctrl-S and
form submit. The references sidebar reflects the last settled save, not every keystroke —
**document this**.

**Rejected:** a delayed `ShouldBeUnique` job. It follows the `ProjectImportJob`
precedent, but it silently never runs when no queue worker is up, and unlike an import
nothing visibly fails — references would just quietly rot. `ImportController` already
carries an inline/background toggle precisely because a worker may not exist.

### 2.6 Snapshot-after, with a seeded baseline

Revisions store the value **after** each change, so the newest row always equals the live
column and compare-with-current is natural.

Before the first-ever revision of a field is written, a `baseline` revision capturing the
**pre-edit** value is seeded. A migration backfills one `baseline` per existing non-empty
registered field, so nothing already written is stranded.

**Why this matters.** All 14 fields already hold data in existing installs and no
revision exists for any of it. Without the baseline, the first autosave of an existing
scene makes the text as it stood before today unrecoverable — precisely the case someone
opens history to fix.

**Rejected:** storing the pre-change value (undo-log style). Elegant on write and needs no
backfill, but the current value never appears as a history row (the list and compare
picker must synthesize a fake "current" entry), and coalescing *inverts* — you must keep
the oldest value in the window rather than overwrite with the newest, which is easy to
get subtly wrong in the feature that can least afford it.

---

## 3. Endpoint, registry, conflicts

### 3.1 One generic field endpoint + an allow-list registry

`PATCH /revisions/{type}/{id}/{field}` → a single `FieldAutosaveController`.

`App\Support\AutosavableFields` maps a **type slug** (never a raw class name taken from
the URL) to model class, per-field validation rules, kind (rich HTML / markdown / plain),
and coalescing window. It sits beside `RichTextFields` and references it for the rich
subset rather than absorbing it — `RichTextFields` has a narrower contract (the
rich-HTML feature) that must not be widened to markdown and plain fields.

A `HasRevisions` trait (alongside the existing `HasSiblingPosition` /
`SanitizesRichHtml` concerns) resolves the owning project for authorization: `Act`,
`Event`, `CodexEntry`, `Plotline` have a `project()` relation; `Chapter` walks
`act.project`; `Scene` walks `chapter.act.project`; `Project` returns itself.

**Why generic.** It delivers "future fields must implement the same functionality" as a
one-line registry entry — no new route, controller, Form Request or test scaffolding per
field. **Rejected:** per-model endpoints (7 near-identical controllers today, one more
per future model); reusing the existing `update` actions with partial payloads (every
cross-field rule in `UpdateSceneRequest` would need `sometimes`, weakening the validation
that protects the real form).

### 3.2 The registry is the security boundary

The type slug resolves through the registry map only. Authorization walks up to the
project via `HasRevisions` and mirrors `ProjectPolicy::update`. Per-field rules are taken
from the registry so they cannot drift from the Form Requests.

### 3.3 Conflicts: per-field base-hash optimistic lock → 409

The editor remembers a sha256 of the value it loaded, and updates it after each
successful save. Every PATCH sends that base hash; the server compares it against a hash
of what is actually stored and returns **409**, refusing the write, on mismatch. The
indicator becomes "Changed elsewhere" with Reload / Keep mine / Compare. The full form
submit carries the same check via a hidden input.

**The failure being prevented:** you edit a scene on your laptop, then hit Save in a
day-old tab on your desktop; the stale tab's textarea posts over the newer text.

**Why per-field, not `updated_at`:** an entity-level token is per-row, so autosaving a
scene's `notes` bumps `updated_at` and would make the `contents` editor *on the same
page* believe it is conflicted. A base hash also needs no schema column and handles the
never-revised case.

**Rejected:** last-write-wins recovered from history (the text is technically recoverable,
but the user is never told it happened and may not notice for days, past the point they
remember which version was right); server-side locking (presence tracking and stale-lock
expiry is heavy machinery for a two-tab, single-user problem).

### 3.4 Client-side resilience

The lower-right indicator is a **state machine** — idle / saving / saved / retrying /
conflict / session-expired / error — not just a spinner. Failed saves retry with backoff.

Every pending value is mirrored to `localStorage` keyed `type:id:field` and cleared on
success, so a crash, a closed laptop or an expired session leaves the text recoverable:
on next load, if a local draft differs from the server value, offer **"restore unsaved
changes"**.

**419 gets its own explicit message**, not a generic error. The scenario: a writer leaves
a tab open for hours, the session expires, and every autosave fails silently while they
keep typing. This is the catastrophic case and must be treated as a first-class state.

---

## 4. Retention

### 4.1 Prune via Laravel's native `Prunable`

`Revision` uses `MassPrunable`; `php artisan model:prune --model=App\Models\Revision`
gives the manual run and `--pretend` dry run for free, scheduled `->daily()` in
`routes/console.php`. **No custom scheduled command** — this satisfies the spec's
"pruning script, runs every day, can be run manually" with framework semantics.

### 4.2 What survives

Delete `automatic` revisions older than `config('revisions.retention_days', 90)` **where
`label IS NULL`** and **excluding the newest revision per `(entity, field)`** — a single
portable `id NOT IN (max(id) group by …)` subquery, no window functions, valid on all
five engines.

Three non-negotiable safety rules:

1. Never prune a non-`automatic` revision (manual / revert / import / baseline).
2. Never prune a labeled revision.
3. **Never prune the newest revision of a field** — otherwise a scene untouched for a
   year silently loses its entire history and there is nothing left to compare or revert
   to.

90 days covers a full drafting season; a writer who wants a moment kept forever labels
it, which is what labels are for.

**Rejected:** keep-newest-N-per-field (needs `ROW_NUMBER`, a portability claim
`multiple-database-engines` would have to verify); 30 days (inside a single novel's
revision cycle — loses "what did this chapter look like before the restructure", the
motivating case); never-prune-by-default (the table grows without bound and the daily run
becomes a no-op).

### 4.3 Purge ≠ prune

Because everything except `automatic` is exempt, exemptions are a **one-way ratchet** —
imported revisions, and a writer who hit Save 5,000 times over two years, never age out.
There must be a bulk way to drop them that is not one-by-one.

* **Prune** = the unattended, safety-preserving daily sweep (§4.1–4.2).
* **Purge** = an explicit, destructive action the user asks for.

A **"Revision storage" panel in project settings** shows counts and total size broken
down by origin (automatic / manual / labeled / imported), with bulk-delete actions per
category and per age ("imported", "auto older than 1 year"), behind the existing
`x-dialog` confirm. An artisan `revisions:purge` command exposes
`--project / --category / --before / --dry-run`. **Both call one `RevisionPurger`
service** so the rules live in exactly one place.

Rationale for shipping the UI and not just the command: this app's users are amateur
writers who will never open a terminal — command-only means the ratchet is never released
in practice.

---

## 5. History & compare

### 5.1 Per-field history page

Route mirrors the PATCH: `GET /revisions/{type}/{id}/{field}`. Lists date, author, label,
origin badge, and marks the current value. Label search is a portable `LIKE` filter.
**List queries select explicit columns and never hydrate `value`** (§1.1).

### 5.2 Revert is non-destructive

Reverting copies the old value onto the live column and records a **new** revision with
`origin: revert` (therefore never pruned), labeled e.g. "Reverted to 14 July 09:12". It
runs through the same sanitization/validation as a normal save and takes the same 409
conflict check.

History only ever grows: you can undo a revert by reverting again, and the state you
reverted away from is still there. **No user action ever deletes history** except the
explicit purge in §4.3.

**Rejected:** truncating history after the reverted point (a mis-click permanently
destroys work, in the feature whose entire job is preventing that); loading into the
editor without saving (interacts badly with autosave, which commits it two seconds later
anyway).

### 5.3 Compare diffs the prose projection

Rich-HTML fields diff `RichText::toPlainText()` output — word-level, via
**`jfcherng/php-diff`** — so the writer sees prose changes, not `</p><p>` churn. Markdown
and plain fields diff their raw stored text, because there the markup *is* the content.

When two revisions differ only in HTML but not in projected text, the view says
**"formatting changed only"** rather than rendering an empty diff.

**Rejected:** diffing raw HTML always (one rule for all 14 fields, but comparing two rich
descriptions becomes tag-soup reading — unusable for this audience); an HTML-aware
structural diff (best possible output, but no off-the-shelf PHP library does it well, so
it is a custom build in the riskiest part of the feature).

---

## 6. Libraries

**No revision package is adopted.** `owen-it/laravel-auditing`,
`overtrue/laravel-versionable` and `venturecraft/revisionable` were considered; the first
two are per-model-save rather than per-field (the spec wants a history page *per field*),
and all of them own the schema, so `label` / `origin` / coalescing / "never prune tagged"
mean bending the package harder than writing the one migration + model + service this
actually is. `spatie/laravel-activitylog` is an excellent audit trail but a log, not a
revision store — revert and compare would be entirely DIY. This follows CLAUDE.md's
"don't introduce patterns without clear value".

**Adopted:**

* **Laravel's native `Prunable` / `model:prune`** — §4.1.
* **`jfcherng/php-diff`** — word-level prose diff, §5.3. (`sebastian/diff` is already
  present via PHPUnit but is line-based unified diff — wrong output shape for prose.)

> [!WARNING]
> Laravel 13 / PHP 8.5 compatibility for `jfcherng/php-diff` was **not verified** — this
> analysis predates the Laravel 13 release. Confirm on Packagist before committing, and
> have a fallback position if it is unmaintained.

---

## 7. Field registry (v1) — 14 fields

| Model | Fields | Kind |
|---|---|---|
| `Project` | `description` | rich HTML |
| `Project` | `dedication`, `acknowledgements`, `preface`, `postface` | Markdown (front & back matter) |
| `Project` | `rights` | plain (max:1000) |
| `Act` | `description` | rich HTML |
| `Chapter` | `description` | rich HTML |
| `Plotline` | `description` | rich HTML |
| `Event` | `description` | rich HTML |
| `Scene` | `description`, `notes` | rich HTML |
| `Scene` | `contents` | Markdown — the critical field, 60s window |
| `CodexEntry` | `description` | rich HTML |

Two corrections to the spec, both deliberate:

* **`Plotline.description` is included.** The spec's list omits Plotline, but it has an
  identical rich `description` and excluding it leaves an inconsistency a user hits
  within a day. Treated as a spec oversight.
* **`CodexAttributeValue.value` is excluded.** Those values already have time-travel
  semantics via `AttributeTimeline` (resolved "as of" an event). Stacking edit-time
  history on top of story-time history is a genuine design conflict, not merely scope.
  **Document this exclusion as deliberate** so it is not "fixed" later.

---

## 8. Import / export

* **EPUB and PDF exports: never include revisions.** (Per spec, unchanged.)
* **Zip export: optional**, via a toggle on `ExportRequest` mirroring the existing media
  toggle, and an `includes_revisions` flag in `data/manifest.json` beside
  `includes_media`.
* **Layout:** one file per field holding that field's whole history as an array —
  `…/scene-N/revisions/contents.json`. **Not** one file per revision: a heavily-edited
  scene would otherwise add hundreds of zip entries.
* **On import:** `created_at` is preserved so the history still reads truthfully;
  `user_id` is remapped to the importing user; every imported revision gets
  `origin: import` and is therefore exempt from age-pruning.

**Why the exemption.** An import is an explicit act of preservation — restoring a backup
and watching its history evaporate overnight would be indefensible. Rewriting `created_at`
to import time was rejected for the opposite reason: every revision would claim to have
been written on restore day, breaking compare-by-date entirely. The exemption is what
makes the bulk purge in §4.3 mandatory rather than optional.

---

## 9. Formerly-open questions — resolved 2026-07-22

All eleven items are answered. Each is grounded in code that was read, not assumed;
§11 records the findings that forced several of these.

### 9.1 New/unsaved entities

Server autosave stays **off** on create forms — `chapter_id` and `name` are `required`,
so there is no valid partial row to PATCH and no id to PATCH against.

But the §3.4 **`localStorage` mirror does cover create forms**, keyed
`new:<type>:<parent-id>:<field>` (the parent id is required, or two "new scene" tabs for
different chapters collide). The draft is cleared on successful create.

**Why.** The create form is where the worst loss happens: paste 3,000 words, session
already expired, hit *Create* → **419**. Laravel's `TokenMismatch` path does **not**
flash old input the way a `ValidationException` does, so the text is simply gone — worse
than anything on the edit page, where the DB at least holds a copy. No new machinery:
the mirror is being built for §3.4 regardless.

**Rejected:** create-a-draft-row-on-first-keystroke (nullable columns or a draft state
leaking into the story tree, exports, search and `SceneReferenceMatcher` — a large blast
radius for one form).

### 9.2 Baseline timing and timestamp

Lazy: no revision at create time. On a field's **first-ever** revision, insert the
`baseline` holding the pre-edit DB value with `created_at = $model->updated_at` and
`user_id` = the project owner. The backfill migration for existing rows uses the
**identical code path** — one implementation.

**Why `updated_at` and not `now()`.** A baseline stamped `now()` claims the pre-autosave
text was written the moment you first touched the field today, which breaks
compare-by-date for the entire era the baseline exists to protect. `updated_at` is the
tightest *true* bound: the value provably held from then on.

The history list renders it as **"Baseline — value before revision history"**, not as a
normal edit row, so the borrowed timestamp is never misread as an edit.

### 9.3 Routes and slug vocabulary

Split prefixes — the write endpoint is not named after its side effect:

| Verb | URL | Route name |
|---|---|---|
| PATCH | `/autosave/{entity}/{id}/{field}` | `autosave.update` |
| GET | `/revisions/{entity}/{id}/{field}` | `revisions.index` |
| GET | `/revisions/{entity}/{id}/{field}/compare?from=&to=` | `revisions.compare` |
| POST | `/revisions/{revision}/revert` | `revisions.revert` |

Slugs mirror the app's own URL segments: `project`, `act`, `chapter`, `scene`,
`plotline`, `event`, `codex` (matching the existing `/codex/{codexEntry}/edit`).

`AutosavableFields` is the single map. The route carries
`->whereIn('entity', AutosavableFields::slugs())` so an unknown slug **404s at the
router** and never reaches the controller; a test asserts every registry slug round-trips
to a real model.

**Why `{entity}` and not `{type}`.** `/projects/{project}/codex/{type}` already exists,
where `{type}` means `character|location|organization`. A second `{type}` meaning "which
model" one segment away would be actively confusing.

### 9.4 History entry point in the UI

A new **`x-autosave-field`** wrapper replaces the hand-rolled 4-line block
(`<div>` → `x-input-label` → `x-wysiwyg` → `x-input-error`) currently repeated ~14 times:

```blade
<x-autosave-field entity="scene" :model="$scene" field="contents" :label="__('Contents')" />
```

It renders the label row (label left, history icon-link right, styled like
`x-icon-edit-link`), the editor, the error, and that field's inline indicator. Kind and
rows come from `AutosavableFields`, so a future field is **one registry line + one blade
line**.

> [!NOTE]
> The wrapper needs a **`plain` kind**, not just wysiwyg: `Project.rights` is a raw
> `<textarea>` at `resources/views/projects/edit.blade.php:73`, not an `x-wysiwyg`.

The history page is **per-field**, with a field switcher at the top listing the entity's
other registered fields. **Rejected:** a separate per-entity all-fields view — a second
route and query shape for navigation only, since compare and revert are inherently
per-field.

### 9.5 Indicator placement — both, with an escalation rule

Confirmed nothing occupies the lower-right corner (`x-modal` is the only fixed-position
component, at `z-50`).

* **Inline, per field** — each `x-autosave-field` shows its own state in the label row.
  Precise: you always know *which* field.
* **Global, lower-right** — a fixed badge subscribing to a shared Alpine store, showing
  **worst-state-wins** and only when it matters (saving / retrying / conflict /
  session-expired / error). Invisible at idle, fades after *saved*. Clicking it scrolls
  to and focuses the offending field.

Precedence: `session-expired > conflict > error > retrying > saving > saved > idle`.

**Why both.** `scenes/edit` has 3 autosaving fields, `projects/edit` has **6** — one
global indicator would have to collapse six state machines into one word, and "Changed
elsewhere" becomes unanswerable. But per-field-only fails the catastrophic case: a writer
3,000 words down in `contents` has scrolled the label off-screen, so a session expiry
fails *where they cannot see it*. Near-free to build: the store is already required for
the retry queue and the mirror, so the badge is a subscriber, not new machinery.

### 9.6 419 recovery — sign in in a new tab, auto-replay

`X-Requested-With: XMLHttpRequest` is set globally in `bootstrap.js`, so `expectsJson()`
is true: an expired session returns **401 JSON**, a stale token **419**. Distinct codes,
but identical from the writer's chair — they **collapse into one indicator state**.

The indicator escalates to *"Session expired — your work is safe. [Sign in]"*, opening
`/login` with `target="_blank" rel="noopener"`. The original tab's DOM is never touched,
so the text cannot be lost by the recovery itself. The store auto-replays the queue on
`focus` / `visibilitychange`.

**Why this works with zero token plumbing.** `app.js:34` already does
`window.axios.patch(url)` with no explicit CSRF header, proving the `XSRF-TOKEN` **cookie**
path is live — and axios 1.x re-reads that cookie **per request** for same-origin URLs.
It is not frozen at page load the way `<meta name="csrf-token">` would be. Logging in on
the second tab rotates the shared cookie and the original tab heals by itself.

> [!WARNING]
> **Signing in as a different user needs its own state.** The replay then hits
> `ProjectPolicy` and returns **403**. Rendered as a generic error, the writer sees "save
> failed" forever with no idea why.

**Rejected:** an in-page modal login (our JS handling raw credentials, a new JSON login
route, duplicated lockout logic, and it breaks the moment auth gains a second factor).

### 9.7 `localStorage` discard rule

Each entry stores `{ value, baseHash, savedAt }`. Because the mirror is cleared on
success, a surviving entry means *by definition* work that never reached the server. On
load:

| Condition | Action |
|---|---|
| `value === server value` | Drop silently (it landed, or was undone) |
| `baseHash === hash(server)` | Clean unsaved work → banner: *"Unsaved changes from 21 July 14:02"* — **Restore** / **Discard** |
| `baseHash ≠ hash(server)` | Server moved on → **Compare** / **Discard** only, **never a bare Restore** |

**No age-based deletion** — age only changes the wording ("from 3 weeks ago"). Storage is
bounded instead: evict oldest-first on `QuotaExceededError` or above a ~4 MB budget.

The banner is **inline per-field, never a modal** (`projects/edit` has 6 fields).

**Why the hash and not just the value.** You type on your laptop (draft stranded), then
edit the same scene properly on your desktop. Weeks later the laptop offers **Restore**
— which would silently clobber the newer desktop text. The base hash is what
distinguishes "unsaved work" from "stale draft the server has moved past".

### 9.8 Payload cap and rate limiting

> [!WARNING]
> **Every long-text column in this app is `$table->text()`** — `scenes.contents`,
> `scenes.notes`, all `description`s, all four front-matter fields, `projects.rights`.
> On MySQL/MariaDB that is **65,535 bytes**. Today this is a latent bug you hit once a
> year on form submit; autosave turns it into a repeating, silent one, on a field whose
> indicator the writer may not be able to see.

* The migration widens the 14 live columns to `longText()` **and** creates
  `revisions.value` as `longText()`.
* The registry carries a per-field **character** cap (`Scene.contents` 1,000,000;
  `rights` 1,000; descriptions 100,000), enforced identically by the autosave endpoint
  and the existing Form Requests so they cannot drift. Note `contents` has **no `max:`
  at all** today — only `new ValidMarkdown`.
* Route gets `throttle:120,1`. A 2-second debounce across 3 fields is ~90 req/min for a
  fast typist, so anything tighter fires on legitimate use.
* **429 maps to the *retrying* state**, honouring `Retry-After` — never to *error*.

**Portability, verified:** the widening is a real change on **MySQL/MariaDB only**.
Laravel's grammars map `text()` and `longText()` identically elsewhere — pgsql `text`,
sqlite `text`, sqlsrv `nvarchar(max)`, all already unlimited. This is the kind of claim
`multiple-database-engines` wants confirmed rather than assumed.

### 9.9 Size accounting — a `size_bytes` column

`SUM(LENGTH(value))` is not portable on **two** axes — not just semantics, but the
function name:

| Engine | `LENGTH()` returns | Byte-exact function |
|---|---|---|
| MySQL / MariaDB | bytes | `LENGTH()` |
| PostgreSQL | characters | `octet_length()` |
| SQLite | characters | — |
| SQL Server | **no `LENGTH()`** — `LEN()`, which also ignores trailing spaces | `DATALENGTH()` |

So: add an unsigned integer **`size_bytes`** to `revisions`, set from `strlen($value)` on
every insert **and every coalescing overwrite** (same write path, one line). The storage
panel and the purge preview become a plain `SUM(size_bytes)` group-by-origin — identical
on all five engines, no raw SQL, and it **never hydrates `value`**, honouring §1.1's read
rule. Also lets prune/purge report "freed 12 MB". The backfill migration must populate it.

### 9.10 `word-count` interplay

`.specs/draft/word-count` wants **cached** counts rolled up scene → chapter → act →
project. Naively that invalidates 4 rows per write; at a 2-second debounce, ~120 rollup
writes per minute per writer, all contending on the same `projects` row.

Autosave therefore **publishes a constraint** rather than implementing counts:

* The live in-editor counter is **client-side**, computed from the TipTap doc — instant,
  no round trip, and it works on the create form too.
* The **persisted** rollups recompute only on the §2.4 coarse triggers (blur / Ctrl-S /
  form submit), never on debounce — the identical rule §2.5 already sets for
  `SceneReferenceMatcher`.
* Autosave defines **one seam**: a `SceneContentsChanged` domain event fired on coarse
  triggers, which both the matcher and word-count subscribe to. Autosave itself knows
  nothing about counts.

**Rejected:** deriving counts from revisions — couples a nice-to-have to the pruning
rules, and coalesced or pruned history makes the counts wrong.

### 9.11 Retention config exposure

`retention_days` **is** editable, in admin settings, as a `RevisionSetting` singleton
following the `ImportSetting` pattern exactly (lazily seeded from `config/revisions.php`,
`access-admin` gate, `UpdateRevisionSettingRequest`). Bounds: `min:7`, `max:3650`; there
is deliberately **no "0 = never"** — the §4.3 purge panel is the release valve.

Because lowering it destroys history irrecoverably and silently, it is **confirm-gated
server-side**:

1. You submit a lower number.
2. The controller counts what the next prune would remove — **using the real prune query
   object**, the same one behind `model:prune --pretend`, so the figure can never be
   stale or wrong.
3. It returns a confirmation: *"This will permanently delete 1,247 versions on the next
   nightly cleanup."* — **Confirm** / **Cancel**. Nothing changes until confirmed.

Raising the value skips the confirmation (it cannot delete anything). Works without
JavaScript and is straightforward to feature-test.

**Rejected:** a live count fetched as you type (extra endpoint; a slow or failed lookup
shows a blank or stale figure exactly when accuracy matters); a bare "are you sure"
(the warning everyone clicks through).

### 9.12 Testing strategy

There is currently **no JS test tooling at all** — no Dusk, no vitest; every test is
PHPUnit. That is fine for today's thin TipTap/Alpine glue, but §3.4 is real decision
logic that is *only* reachable once something has already gone wrong, so nobody exercises
it by hand either.

* **PHPUnit** covers the server contract: happy path, non-owner 403, validation failures,
  409 conflict, 419/401, 429, coalescing (a second save inside the window updates rather
  than inserts), every §4.2 pruning safety rule, revert non-destructiveness, the
  retention confirm step, and the backfill migration.
* **Add vitest**, testing logic only. The decision logic lives in a plain module
  (`resources/js/autosave/store.js`) — state transitions, draft triage, retry timing,
  response-code mapping — with the Alpine component as a thin adapter over it. No
  browser, no DOM. One devDependency; it forces the better structure anyway.
* **Manual checklist** via the `run-imagoldfish` skill for the genuinely browser-y cases:
  a real expired session, `localStorage` quota exhaustion.

> [!NOTE]
> This adds `npm run test` as a canonical command — **CLAUDE.md's Commands section must
> be updated** when it lands.

Parallel-paratest constraints stand: no shared state, and anything time-based uses
`travel()` rather than real sleeps.

### 9.13 Hash authority — the server, solely

*(Not in the original §9; surfaced by §11.2–11.3 and it breaks §3.3 as written.)*

The client **never hashes anything**. The page render emits the stored value's hash as a
data attribute. Every PATCH response returns `{ value, hash, revision_id, saved_at }`
computed from **what was actually persisted**, and the client adopts the hash. It keeps
the returned value as its "last known server value" for the byte-identical check and the
`localStorage` `baseHash` — but **never writes it back into the editor**, which would
yank the caret mid-sentence.

**The bug this prevents.** §3.3 says the editor *"updates [the base hash] after each
successful save."* If it updates it to a hash of what it *sent*, that hash never matches
what is stored (§11.2, §11.3) — so the **second** autosave of every rich field 409s with
"Changed elsewhere", on a document nobody else touched. Guaranteed, on all 8 rich-HTML
fields, in the feature's core loop.

---

## 10. Dependencies on sibling specs

| Spec | Relationship |
|---|---|
| `.specs/draft/expand-tip-tap` | **Was the hard prerequisite — now substantially resolved.** Tables, images, task lists, strikethrough, underline, and callouts are all decided-to-support; the rest of the original inventory (nested blockquotes, hard breaks, reference-style links, definition lists, raw HTML blocks) is verified safe or gracefully degrading, not destructive. See §11.4 (updated) and that spec's own "Synthesis" section. **Still open there:** the exact fallback-warning policy for the small remaining attribute/structure-level losses (merged table cells, resized image dimensions, an HTML wrapper tag's attributes) — see §11.5.2. Footnotes were split out to `.specs/draft/footnote-plugin` (no official TipTap extension exists) and are not blocking — a footnote degrades to plain visible text today, it does not get destroyed. |
| `.specs/draft/data-loss-warnings` | **Hard dependency.** Owns the gap in §2.3 — short fields and relations do not autosave, so a dirty-form guard is required for this feature to be safe. |
| `.specs/draft/multiple-database-engines` | Constrains §1.1, §4.2, §9.8, §9.9 — all DDL and queries must be portable across five engines; no window functions without verification. The `text()` → `longText()` widening (§9.8) and the `size_bytes` column (§9.9) are both direct consequences. |
| `.specs/draft/editor-interface` | Shares the editor surface; the indicator, `Ctrl-S` handling and dirty state must be designed together, not twice. The `x-autosave-field` wrapper (§9.4) is the shared seam. |
| `.specs/draft/archive-and-delete` | Currently one line. When archiving lands, revisions must survive alongside an archived entity rather than being swept as orphans (§1.3). |
| `.specs/draft/word-count` | See §9.10 — autosave publishes the `SceneContentsChanged` coarse-trigger seam; word-count subscribes. |
| `.specs/draft/keyboard-shortcuts` | Unresolved: whether `Ctrl-S` (§2.4) collides with anything that spec claims. |

---

## 11. Verified code findings from the 2026-07-22 session

Each was read in the codebase, and each changed a decision above.

### 11.1 `text()` columns cap at 64 KB on MySQL

Every long-text column is `$table->text()`. See §9.8 — this is why the migration widens
them.

### 11.2 `SanitizesRichHtml` is a **set-mutator**, so stored ≠ sent

`app/Models/Concerns/SanitizesRichHtml.php` runs HTMLPurifier in an `Attribute::make(set:
…)` — a deliberate choke point chosen because mutators still run under
`WithoutModelEvents`. `SanitizeHtml` (the rule) only *validates*; it does not clean.

Consequence: for all 8 rich-HTML fields, the value the client PATCHes is **not** the
value that lands in the column. This is the primary driver of §9.13.

### 11.3 TipTap re-serializes Markdown, so stored ≠ sent there too

`resources/js/wysiwyg.js` mounts `@tiptap/markdown` with `contentType: 'markdown'` and
syncs via `getMarkdown()`. Stored Markdown is parsed into a ProseMirror doc and
re-serialized, so it comes back normalized (`_em_` → `*em*`, bullet markers, wrapping).

Consequence beyond §9.13: **the first edit of every pre-existing scene will produce a
whole-document diff** in the compare view. The baseline (§9.2) preserves the original, so
nothing is lost — but the expanded spec must say so, or it reads as a bug.

### 11.4 The editor cannot round-trip tables, images or footnotes — **was the blocker, now resolved**

> **Update, `expand-tip-tap` session:** the "unverified" item below has been verified against
> actual package source, not assumed. Tables, images, and task lists all ship real
> `parseMarkdown`/`renderMarkdown` handlers and are decided-to-support; strikethrough,
> underline (via `<u>` passthrough), and callout/alert blocks are also decided. Footnotes
> have no official TipTap extension (confirmed via registry 404) and were split into their
> own spec, `.specs/draft/footnote-plugin` — not a blocker, since a footnote degrades to
> plain visible text today rather than being destroyed. The rest of the original
> "unrepresentable content" concern (raw HTML blocks, definition lists, reference-style
> links, nested blockquotes, hard breaks) is now verified safe or gracefully degrading too.
> See `expand-tip-tap`'s "Synthesis" note: **nothing left in its inventory deletes content
> outright** — the only remaining decision is the fallback-*warning* policy for smaller
> attribute/structure-level losses (§11.5.2). The narrative below is kept as the historical
> record of what triggered the split.

`wysiwyg.js` configures **`StarterKit` only**. `node_modules/@tiptap` contains no Table
and no Image extension. But `ValidMarkdown` accepts anything CommonMark parses, so a
scene *can* contain a Markdown table — from a `.zip` import, a paste from another writing
tool, or a scene written before the TipTap editor shipped.

Such a scene hydrates into a doc that cannot represent the table, and `getMarkdown()`
returns it flattened.

**Today** that requires a deliberate Save click. **With autosave, two seconds of typing
anywhere in the scene destroys it silently** — same destruction, no consent. The baseline
revision does make it recoverable for the first time, but "recoverable if you ever
notice" is not safe.

What was established before we stopped:

* TipTap 3 **does** ship official `Table` and `Image` extensions. Footnotes have **no**
  official extension — community only.
* `@tiptap/markdown` (3.27.1, built on `marked` ^17) dispatches conversion **per node**:
  the manager reads `markdownName`, `parseMarkdown`, `renderMarkdown` and `priority` from
  each extension. So a node type only survives a Markdown round-trip if its extension
  ships those handlers.
* **Unverified:** whether the official `Table` / `Image` extensions ship Markdown
  handlers. Not installed, so it could not be checked. Same shape as the
  `jfcherng/php-diff` warning in §6 — confirm on the package, do not assume.
* Adding tables is not one line: extension + toolbar/slash-menu entries + Markdown
  round-trip verification + letting `HtmlSanitizer` through `<table>` for the 8 rich-HTML
  fields + tests.

**Decision: this is editor scope and comes first.** A separate TipTap capability spec
must establish what the editor can and cannot round-trip before autosave commits to a
behaviour. See §10.

### 11.5 Still open, pending the TipTap spec

1. **The dirty-only rule.** Proposed but **not confirmed**: *never autosave a field the
   writer has not actually typed in*, so merely opening a scene can never write anything.
   Strongly recommended regardless of the TipTap outcome — it is what makes reading safe.
2. **Warning on unrepresentable content — reframed, still open.** `expand-tip-tap` resolved
   what the editor supports; nothing left in its inventory destroys content outright (see
   §11.4 update). So this is no longer "warn before content gets destroyed" — it's whether
   to surface the smaller remaining attribute/structure-level losses (a merged table cell,
   a resized image's dimensions, an HTML wrapper tag's attributes) via an explicit
   config-list warning, rather than a fuzzy diff. `expand-tip-tap` leans toward
   flatten-and-warn-from-a-list but has not finalized it.
3. Whether `Ctrl-S` collides with `.specs/draft/keyboard-shortcuts`.
