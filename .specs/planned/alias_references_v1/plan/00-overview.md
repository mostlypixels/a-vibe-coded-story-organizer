# Alias references v1 — Plan overview

This manual is never implemented or moved. It fixes the execution order, the binding design
decisions, and the invariants every task must preserve. Task files (`NN-*.md`) are moved to
`plan/implemented/` as each is finished and verified.

Detail lives in the sibling spec docs: `../expanded/overview.md`, `../expanded/data-model.md`,
`../expanded/architecture.md`, `../expanded/ui.md`, `../expanded/testing.md`,
`../expanded/open-questions.md` (all 8 open questions there are resolved — read the two
`[!NOTE]` blocks at the top before starting any task).

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-data-model.md` | `scene_codex_entry` pivot migration, `Scene::codexReferences()` / `CodexEntry::referencingScenes()` relations. |
| 02 | `02-reference-matcher.md` | `App\Services\SceneReferenceMatcher` — the whole-word Unicode matching rule, `syncScene()` / `syncProject()`. Unit-tested in isolation, not yet wired to any controller. |
| 03 | `03-scene-save-integration.md` | Wire `syncScene()` into `SceneController::store`/`update`. |
| 04 | `04-codex-entry-save-integration.md` | Wire `syncProject()` into `CodexEntryController::store`/`update`, with the create-always / update-only-if-changed rule. |
| 05 | `05-codex-edit-ui.md` | Codex entry edit page: aliases help text + "Referenced in scenes" sidebar (timeline-ordered). |
| 06 | `06-scene-edit-ui.md` | Scene edit page: "Codex references" sidebar. |
| 07 | `07-import-regeneration.md` | Regenerate references once during project import, after the Codex phase; confirm export writes none. |

Dependencies: 02 → 01; 03 → 01, 02; 04 → 01, 02; 05 → 01; 06 → 01; 07 → 01, 02. Tasks 05 and 06
are UI-only read paths — they render whatever is in the `scene_codex_entry` pivot, so their
tests seed rows directly (`attach()`) rather than depending on 03/04's write paths. This keeps
every task independently verifiable, per the plan-implementer contract.

## Binding decisions (do not re-litigate)

- **Matching scope = `Scene.contents` only.** Never `description` or `notes` (`notes` is
  private — see `documentation/architecture.md` → *Scene sharing*).
- **Matching terms = entry `name` AND every `CodexAlias.alias`**, not aliases alone (resolved in
  grilling — see `open-questions.md`'s second `[!NOTE]`).
- **Aliases shorter than 3 characters are excluded from matching.** `name` has no length floor.
  Not a validation rule — a short alias can still be saved, it just never drives a match.
- **Whole-word, case-SENSITIVE, Unicode-aware.** A character named "Luck" must not match the
  common noun "luck" — no `i` flag, no case-folding anywhere in the matcher. Hyphens are part of
  the word, not a boundary. No stemming, no fuzzy matching, no plural handling.
- **Both sides normalized to Unicode NFC before matching** (`ext-intl`'s `Normalizer`, newly
  declared in `composer.json`), so visually-identical accented text (French/Italian names) from
  different input sources always compares byte-equal. Malformed UTF-8 in a scene's `contents`
  must be caught and logged, never allowed to block the scene save.
- **Overlapping matches: both/all matching entries link independently.** No precedence or
  "longest match wins" rule — including when two entries happen to share identical alias/name
  text (the candidate map is `term → array<codex_entry_id>`, never a single id).
- **Every recompute is a full `sync()`**, never an incremental add/remove. `syncScene()` replaces
  one scene's full match set; `syncProject()` replaces every scene's in that project. No task may
  implement this as attach-only.
- **Sync triggers:**
  - Scene store/update → always `syncScene($scene)`.
  - Codex entry **store** → always `syncProject($project)` (a new entry's alias/name set is
    trivially "changed" from nothing).
  - Codex entry **update** → `syncProject($project)` **only if** the alias set or name actually
    changed vs. before the save (compare inside the transaction).
  - Codex entry / scene **delete** → no application code; `cascadeOnDelete` on both FKs handles
    pivot cleanup at the DB level.
- **No model `booted()` hook.** This is application workflow (needs before/after comparison,
  touches records beyond the model being saved), not a lifecycle invariant — lives in
  `SceneReferenceMatcher`, called explicitly from the controllers. See `architecture.md` → *Why
  not a model booted() hook*.
- **"Timeline order" (codex sidebar) = event order**, `(event_datetime, id)`, the same canonical
  ordering `AttributeTimeline` uses — never manuscript position. Scenes with no assigned event
  sort last (manuscript position as tiebreak among themselves), labeled distinctly.
- **No AJAX.** Both sidebars render server-side on page load from the last-saved pivot state;
  the scene edit sidebar's copy makes this explicit ("Detected from the scene contents on last
  save.").
- **No `MelusineSeeder` changes** in this feature.
- **Never exported, always regenerated on import.** `StaticSiteExporter` writes no
  `scene_codex_entry` data (it's a derived cache — see `data-model.md`'s *Export/import
  impact*). `ProjectImporter::run()` calls `SceneReferenceMatcher::syncProject()` exactly once,
  after the graph-import loop and before marking the `Import` `Completed` — never as a fifth
  `ImportPhase`. See `architecture.md` → *Import/export interaction* for why that hook point is
  resume-safe.

## Invariants every task must preserve

- **Full-resync correctness.** After any scene save or any qualifying codex entry save, the
  `scene_codex_entry` table exactly equals "every entry whose name/alias whole-word-matches this
  scene's/this project's scenes' contents" — no stale rows, no partial updates. See
  `testing.md`'s "stale row" regression test — every task touching the matcher must keep it
  passing.
- **Authorization flows from the Project**, unchanged. Both new read paths hang off already-
  authorized `edit()` actions (`CodexEntryController::edit`, `SceneController::edit`) — no task
  introduces a new authorization surface or bypasses the existing `ProjectPolicy` walk.
- **Word-boundary matching never regresses to substring matching.** The "Mel"/"melody" case from
  the source spec is the canonical regression test; every task that touches the regex must keep
  it passing.
- **Never appears on the public scene share page.** `shared/scenes/show.blade.php` gets no
  "Codex references" card and no other exposure of `scene_codex_entry` — as binding as the
  existing "`notes` is private" rule on that same page. See `architecture.md` → *Never appears
  on the public scene share page* and `testing.md`'s regression test.
- **No purge hook for `scene_codex_entry` on project/user deletion.** The DB-level
  `cascadeOnDelete` chain already reaches it (confirmed in `architecture.md` → *Project/user
  deletion*) — no task may add one; it would be redundant.

## Cross-cutting requirements (every task)

- Ship feature/unit tests with each task (happy path + authorization negative where relevant +
  the invariants above), runnable in isolation via `composer test`.
- Update `CHANGELOG.md` `## [Unreleased]` per task (Added/Changed).
- Append per-task notes/decisions/issues to `../resolution-log.md`.
- Task 02 also adds a short "Scene references" note under the existing *Codex* section of
  `documentation/architecture.md` (a new service + a new invariant is architecturally notable for
  junior devs, per `CLAUDE.md`'s documentation rule).
