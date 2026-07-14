# 05 — Codex entry edit page UI

## Scope

The two codex-side UI additions from the source spec: help text under aliases, and the
"referenced in scenes" sidebar in timeline order.

**Builds:**
- `CodexEntryController::edit`: add `referencingScenes` to the view data —
  `$codexEntry->referencingScenes()->with('chapter.act', 'event')->get()`, sorted by
  `(event->event_datetime, event->id)` with unassigned scenes (`event === null`) sorted last,
  tiebreaking among themselves by `(chapter.act.position, chapter.position, position)`.
- `resources/views/codex/partials/fields.blade.php`:
  - A second help-text `<p>` under the existing aliases paragraph, explaining that matching is
    case-sensitive and whole-word, that aliases under 3 characters are ignored, and that
    overlapping aliases can produce ambiguous matches — see `../expanded/ui.md` for the exact
    copy. This isn't decorative: it's the only place a writer learns why "Luck" or a 2-character
    alias silently never links.
  - A `@if ($entry)`-gated "Referenced in scenes" `<x-card>` in the existing `<x-slot:sidebar>`,
    after the Tags card: empty state, or a list of scene links (`route('scenes.edit', $scene)`)
    each showing act/chapter/scene name and, when assigned, the event title + date. Unassigned
    scenes show a distinct label instead of an event line.

**Does NOT build:** the matching/write logic (tasks 02-04) — this task's tests seed the
`scene_codex_entry` pivot directly via `attach()`, independent of whether the matcher is wired
up, so it's verifiable in isolation.

## Depends on

- **01** (`CodexEntry::referencingScenes()`) in `plan/implemented/`.

## Key decisions already made

- "Timeline order" = event `(event_datetime, id)` order, never manuscript position — see
  `00-overview.md`. Unassigned scenes last, not excluded.
- No new Blade component — reuses `x-card` and plain markup, per `../expanded/ui.md` (below the
  "second caller" bar for a new component).
- The card only renders on `edit`, never on `create` (no entry id, no possible references yet).

## Consult

`../expanded/ui.md` (has the exact Blade snippet), `../expanded/architecture.md` → *Read paths*,
`00-overview.md`.

## Tests (additions to `tests/Feature/CodexEntryTest.php`)

- Help text is present on the edit page (`assertSee`).
- Edit page lists referencing scenes ordered by event datetime (seed scenes with events in
  scrambled creation order, assert rendered order).
- A scene with no assigned event appears last, distinctly labeled, rather than disappearing.
- An entry with no referencing scenes shows the empty state, not an error.
- Non-owner still gets 403 on the edit page (regression guard — existing
  `test_non_owner_is_forbidden_from_every_action` should already cover the new eager load; verify
  it still passes).
