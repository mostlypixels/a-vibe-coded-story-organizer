# Autosave With Revisions — Plan overview

Source design: `../expanded/*.md` (overview, data-model, architecture, ui, testing,
open-questions) and `../handoff.md` (the binding grilling record — where anything here
seems to disagree, `handoff.md` wins). This overview is the manual for the 16 task files
below; it is never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 1 | `01-revisions-table-and-model.md` | `revisions` table, `Revision` model (`MassPrunable`), `RevisionOrigin` enum, `config/revisions.php` |
| 2 | `02-widen-long-text-columns.md` | Migrate the 14 registered columns from `text()` to `longText()` |
| 3 | `03-has-revisions-trait-and-registry.md` | `HasRevisions` trait + `AutosavableFields` registry (declarative wiring, no write logic yet) |
| 4 | `04-revision-recorder-service.md` | `RevisionRecorder`: coalescing writes + baseline seeding |
| 5 | `05-baseline-backfill-migration.md` | Backfill migration seeding baselines for existing installs, via `RevisionRecorder` |
| 6 | `06-field-autosave-controller.md` | `FieldAutosaveController` + routes + conflict/hash (409) + coarse-trigger `SceneReferenceMatcher`/`SceneContentsChanged` wiring |
| 7 | `07-autosave-js-store.md` | `resources/js/autosave/store.js` — pure state-machine decision logic + vitest |
| 8 | `08-autosave-field-component.md` | Alpine adapter + `x-autosave-field` Blade component + dirty-only gating + `localStorage` mirror |
| 9 | `09-wire-views-and-global-badge.md` | Replace the ~14 hand-rolled field blocks across existing edit views with `x-autosave-field`; add the global lower-right indicator |
| 10 | `10-history-and-compare.md` | `RevisionController::index`/`compare`, `RevisionDiffer` (verify `jfcherng/php-diff` or fall back) |
| 11 | `11-revert-action.md` | `RevisionController::revert` |
| 12 | `12-retention-purge-service.md` | `RevisionSetting` singleton, daily `model:prune` scheduling, `RevisionPurger` service, `revisions:purge` command |
| 13 | `13-admin-revisions-page.md` | Admin "Revisions" page: confirm-gated retention form + storage panel |
| 14 | `14-export-revisions.md` | Zip export: `include_revisions` toggle, `StaticSiteExporter` output |
| 15 | `15-import-revisions.md` | Zip import: `ProjectGraphImporter` reads revision sidecars |
| 16 | `16-docs-and-changelog.md` | `documentation/architecture.md` "Revisions" section, `CHANGELOG.md`, note the `data-loss-warnings` gap |

Tasks are ordered by dependency, not narrative — e.g. the JS store (7) is pure logic and
could theoretically be written any time after the controller contract (6) is fixed, but
sits after 6 so its status-code mapping tests can be written against a real, not assumed,
API shape.

## Binding decisions (do not re-litigate)

Resolved in `handoff.md` and this plan's grilling pass — every task must honor these:

* **No draft-vs-published split.** Autosave writes the live column directly.
  (`handoff.md` §2.1)
* **Dirty-only.** Autosave never fires until the writer has actually typed in a field —
  a dirty flag gates the debounce timer, blur-save, and Ctrl-S alike. Opening a record
  to read it writes nothing. (Confirmed in grilling; `open-questions.md` #3)
* **Server is the sole hash authority** (`handoff.md` §9.13). The client never computes
  a hash of what it's about to send; every PATCH response returns a hash of what was
  actually persisted, and the client adopts *that* — never re-derives one from the sent
  payload, and never writes the returned value back into the live editor DOM.
* **Coalescing overwrites in place** within a field's configured window
  (`Scene.contents` 60s, everything else 5 minutes by default); a byte-identical save
  writes no revision at all. (`handoff.md` §2.2)
* **Only `origin: automatic` revisions are ever prunable**, and the prune query must
  never remove a labeled revision or the newest revision of any `(entity, field)` pair,
  even if `automatic` and old. (`handoff.md` §4.2 — this is the feature's single most
  safety-critical invariant; every task touching `Revision::prunable()` or
  `RevisionPurger` must re-verify it with a test.)
* **One generic endpoint, not one per model.** The type slug resolves only through
  `AutosavableFields::REGISTRY`; an unregistered slug 404s at the router
  (`->whereIn('entity', AutosavableFields::slugs())`), never reaching the controller.
  (`handoff.md` §3.1–§3.2)
* **Authorization always walks to the owning `Project`** via `HasRevisions::
  revisionProject()`, mirroring `ProjectPolicy::update` — exactly like every other
  controller in this codebase (CLAUDE.md's authorization rule). Every new
  controller action must have a non-owner-gets-403 test.
* **Validation rules come from the registry, never duplicated** — the autosave endpoint
  and the existing Form Requests must use the identical cap/rule source so they cannot
  drift. (`handoff.md` §9.8)
* **Revert is additive, never destructive.** It writes a new `origin: revert` revision;
  no user action ever deletes history except the explicit purge (task 12/13).
  (`handoff.md` §5.2)
* **Ships independently of `.specs/draft/data-loss-warnings`.** The known gap (short
  fields — `name`, `chapter_id`, `status`, `event_id`, `mentioned_events` — still only
  save on form submit) is documented, not blocked on. (Grilling decision; task 16
  writes the note.)
* **`jfcherng/php-diff` is unverified.** Task 10 checks Packagist/GitHub for PHP 8.5 /
  Laravel 13 compatibility before adding it as a dependency; if unmaintained or
  incompatible, task 10 implements a small hand-rolled word-level LCS diff instead.
  Either way, `RevisionDiffer` is the one class the rest of the app calls — its internals
  are task 10's choice alone.
* **No `doctrine/dbal` dependency needed.** Confirmed absent from `composer.json`/
  `vendor/doctrine` and not required: Laravel 13's schema builder performs a plain
  `text()` → `longText()` column-type change natively. Task 2 must not add
  `doctrine/dbal` "just in case" — if the migration errors requiring it, that itself is
  new information to report, not something to route around silently.
* **Admin surface: a new, dedicated "Revisions" admin page** (task 13) — not folded into
  Export & import or General settings.
* **A distinct `forbidden-after-replay` indicator state** for the session-expired →
  sign-in-as-different-user → 403 case (`handoff.md` §9.6's flagged gap), never folded
  into generic `error`. Owned by task 7 (state definition) and task 8/9 (UI copy).
* **`Ctrl-S` is claimed.** Non-blocking for this plan, but task 16's docs update must
  flag that Ctrl-S-to-flush-and-close is already spoken for, for whoever picks up
  `.specs/draft/keyboard-shortcuts` later.

## Core invariants every task must preserve

* **Authorization-via-project walk** (see above) — never rely on route model binding
  alone.
* **`position`/sibling-ordering invariants** on `Act`/`Chapter`/`Scene` are untouched by
  this feature — no task here writes to `position`.
* **List queries against `revisions` never hydrate `value`** — history index, storage
  panel, and purge-preview queries select explicit columns only (`data-model.md` §1.1's
  read rule; enforced by `size_bytes` existing precisely so `SUM(size_bytes)` never
  needs `value`).
* **`project_id` is always set explicitly** on every `Revision` row (own id for
  `Project`, walked via `HasRevisions::revisionProject()` for everything else) — never
  left to infer from the polymorphic relation, since polymorphic columns carry no FK.
* **Every new migration is portable across sqlite/mysql/mariadb/pgsql/sqlsrv** per
  `.specs/draft/multiple-database-engines` — no window functions, no engine-specific
  DDL. The `whereNotIn(... MAX(id) group by ...)` prune-safety subquery (task 1) is the
  concrete instance to match.
