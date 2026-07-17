# Import — plan overview

Never implemented or moved. Read this before starting any task below; it's the
manual, the task files are the work.

## Source docs

Every task references `.specs/expanded/import/expanded/{overview,data-model,
architecture,ui,testing,open-questions}.md`. Those docs (and the grill that produced
them) are the design; this plan only sequences it into buildable, independently
verifiable steps. Don't re-litigate a decision already made there — if a task file
and an expanded doc ever seem to disagree, the expanded doc wins and the task file
has a bug.

## Execution order

| # | Task | Builds |
|---|------|--------|
| 01 | `01-data-model-foundation.md` | `imports` + `import_settings` migrations, `Import`/`ImportSetting` models, `ImportPhase` enum, `ImportPolicy`, `config/import.php` |
| 02 | `02-archive-validator.md` | `ArchiveValidator` + `ImportRules` — the security gate (zip validity, zip-slip, arborescence allow-list, manifest version, JSON shape, content-sniffed media) |
| 03 | `03-content-sanitizer.md` | `ContentSanitizer` — composes the existing `HtmlSanitizer`/`RichTextFields` allow-list with a reject-on-violation policy for `description.html`/`notes.html`/rendered `contents.md` |
| 04 | `04-project-graph-importer.md` | `ProjectGraphImporter` — id-remapping, the 4 `ImportPhase` methods, anchor reconciliation, position replay, media copy, name-collision suffixing |
| 05 | `05-project-importer-orchestrator.md` | `ProjectImporter` (`start()`/`run()`/`discard()`) — ties 02–04 together with per-phase checkpointing to `Import` |
| 06 | `06-http-layer.md` | `ImportController`, `ImportProjectRequest`, routes, `ImportPolicy` wiring for resume/discard |
| 07 | `07-queued-mode.md` | `ProjectImportJob`, `ImportSetting.run_in_background` wiring in the controller |
| 08 | `08-ui.md` | The Import tab form, the "Import settings" card, the in-progress-imports list (resume/discard buttons) |
| 09 | `09-end-to-end-and-docs.md` | Full export→import round-trip feature test, `documentation/architecture.md` update, `CHANGELOG.md` entry |

Strict dependency order — each task's tests must pass (`composer test`) before the
next task starts, and each task's file(s) move to `plan/implemented/` once done.

## Binding design defaults (do not re-decide these mid-task)

* **New project every time.** Import never merges into or updates an existing
  project. A name collision only ever changes the new project's `name` (timestamp
  suffix), never blocks creation.
* **`data/` is the only source of truth.** `book/` and `README.md` are allowed to be
  present in the zip (real exports have them) but are never read.
* **Ids are always remapped.** The archive's ids are source-installation ids; every
  new row gets a fresh id, and every reference (`event_id`, `plotline_ids`, etc.) is
  looked up through an id map built during import. A reference that doesn't resolve
  is a validation failure, not a dropped relationship.
* **Position is replayed verbatim.** Every ordered row's `position` is set explicitly
  from the archive's JSON before save — never left `null` for the `HasSiblingPosition`
  `creating` hook to re-derive.
* **The main plotline and Start/End bookend events are reconciled, not duplicated.**
  `Project::booted()`'s `created` hook already made a new set for the new project;
  the importer updates those rows in place with **every** field the archive recorded
  (name/color/description, title/datetime/description) rather than inserting a
  second main plotline or a second bookend pair.
* **Content is validated, never silently mutated.** `ArchiveValidator` runs before
  any row is written; `ContentSanitizer` rejects the whole archive on any
  disallowed HTML/Markdown content rather than stripping and continuing (this is
  deliberately stricter than normal form-submission sanitization).
* **Authorization is two different patterns, both intentional.** `POST
  admin.data.import` uses the any-authenticated-user exception (like
  `CrawlerSetting`) since there's no project yet to walk up to. `resume`/`destroy`
  on an existing `Import` use a real `ImportPolicy` (`$user->id === $import->user_id`)
  since by then there is an owner — don't collapse these into one pattern.
  `ImportSettingController@update` uses the any-authenticated-user exception too
  (it's a global admin setting, same as `CrawlerSetting`).
* **Every phase is its own transaction.** `ProjectGraphImporter`'s 4 phases
  (`project`, `timeline`, `story`, `codex`) each commit independently, checkpointed
  onto the `Import` row (`phase`, `id_maps`) — never one transaction for the whole
  import. This is what makes resume/discard possible.
* **Synchronous is the default; queued is opt-in.** `ImportSetting.run_in_background`
  defaults `false`. Validation (`ProjectImporter::start()`) is **always** synchronous
  regardless of the toggle — only the graph-import phases (`run()`) are ever queued.
* **`ImportSetting` and `Import` are two different tables**, not one — settings vs.
  tracking record, same separation `CrawlerSetting` keeps from every content model.

## Core invariants every task must preserve

* Exactly one `Plotline` with `is_main = true` and exactly two `Event`s with
  `is_fixed = true` per project — before and after any import.
* Every ordered entity (`Act`/`Chapter`/`Scene`/`CodexMedia`/`CodexAttribute`) has a
  contiguous `position` sequence within its sibling scope.
* Every controller action authorizes (per `CLAUDE.md`); every new endpoint has a
  feature test covering the negative/403 case.
* Nothing is ever written to disk or the database from an archive that hasn't passed
  `ArchiveValidator` + `ContentSanitizer` first.
* A crashed import (any phase after `project`) always leaves the `Import` row in a
  state a user can act on (resume or discard) — never an orphaned row with no
  recovery path.
