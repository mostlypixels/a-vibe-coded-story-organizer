# 04 — Cap the two codex cards

## Scope

- `codex/show.blade.php`: delete the `x-data="{ showAll: false }"` block, the per-row
  `x-show`, the hardcoded 20, and the "Show all" button. Render `scene-row` capped at
  `config('search.cap')` with the footer link.
- `codex/edit.blade.php`: replace the `<ul>` with the same capped `scene-row` table. Keep
  it full-width below the attribute timeline.
- Add a line to `config/search.php`'s `cap` comment saying the reference cards read it too.
- Both controllers keep passing the full collection; the view caps, as `search.index` does.
- Not in scope: the scene-side cards (task 06).

## Depends on

03.

## Key decisions

- Both cards adopt one shape. The `codex/edit` list loses its `scenes.edit` link — from a
  codex entry the writer is asking "where does she appear", which is a read.
- The `codex/show` card gains an Event column it does not have today.
- Footer link text is `See all :count results`, matching
  `components/search/result-table.blade.php` word for word. No footer at or below the cap.
- The old Alpine hack put every row in the DOM. The replacement must not render the hidden
  rows at all.

## Consult

`expanded/ui.md` → *Cards*. `components/search/result-table.blade.php` for the cap + footer
pattern.

## Tests

`ReferenceListTest`, or extend the existing codex view tests.

- 6 referenced scenes with `search.cap` at 5: 5 rows plus the footer link, and the 6th
  scene's name is absent — `assertDontSee`, which the Alpine version would have failed.
- Exactly `cap` rows: no footer link.
- Both cards, since each is a separate Blade.
