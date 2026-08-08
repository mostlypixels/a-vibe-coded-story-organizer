# 10 — The Tools landing page

`resources/views/tools/home.blade.php` still renders the literal word `stub`, while #89 gave
Story, Timeline and Codex real landing pages.

## Scope

- Replace the stub with **one card per tool** in the same grid the other three sections use:
  title, one sentence saying what it is for, and a link. Two cards today — **Revisions** and
  **Progress**.
- Update `tests/Feature/SectionStubTest.php`, which still expects `stub` for Tools — the same
  edit #89 made for the other three.
- `ToolsController::home()` needs no new data.

## Depends on

07 (the Progress route the second card links to).

## Key decisions

- **Cards, not an `x-recent-list`.** Tools has no entities to list: `RecentlyEdited`'s own
  docblock rules revisions out (immutable, `created_at` only), and "recently created
  revisions" is a strange thing to land on.
- **No live numbers on the Progress card.** The dashboard and the Progress page already show
  that figure; a third copy is one too many.

## Consult

`expanded/ui.md` → *Tools landing page*

## Tests

- The page lists both cards and links to `projects.revisions.index` and `projects.progress`.
- Non-owner → 403.
- `SectionStubTest` no longer expects `stub` anywhere.
