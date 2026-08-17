# 01 — Naming cleanup

Free the word "book" before the feature claims it. Nothing here is about multiple books; it is
the rename that stops "book" meaning three things.

**Depends on:** nothing. First task.

## Scope

- `App\Enums\StoryOverviewMode::Book` (`'book'`) → `Whole` (`'whole'`). Update the label so the
  toggle reads "Whole book" rather than "Book".
- A migration rewriting the stored `projects.overview_render_mode` value `'book'` → `'whole'`.
- `EpubExporter::bookTree()` → `actTree()`, and its callers.
- Sweep the remaining prose uses of "book" in `app/` docblocks that mean *the whole story* and
  now read ambiguously.

**Not in scope:** the archive's `book/` folder rename — that is task 13, with the version bump
it belongs to. Leave it alone here.

## Key decisions

- Rename now, not later. Two meanings of "book" in one codebase is a permanent tax on every
  later reader, and tasks 02–17 all add the third meaning.
- `StoryOverviewMode` follows the project's enum convention: string-backed, `label()`, cast on
  the model, `Rule::enum` in the Form Request.

## Consult

`expanded/architecture.md` → *The "book" naming collision*.

## Tests

- `StoryOverviewModeTest` updated for the new case and value.
- A migration test is **not** needed for the value rewrite alone — but `StoryTest` must prove
  the overview still renders both modes, and `UpdateStoryOverviewModeRequest` still rejects an
  unknown mode.
