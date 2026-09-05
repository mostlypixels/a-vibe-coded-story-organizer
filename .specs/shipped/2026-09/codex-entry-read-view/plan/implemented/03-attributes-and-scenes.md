# 03 — Attributes and referencing scenes

The two sections that carry the entry's substance, and the two that grow.

## Scope

- New `resources/views/codex/partials/attribute-values.blade.php`: for each attribute
  `setOnly()` returns, the attribute name and **every period in timeline order, baseline
  first**. Follow the `<dl>` shape in `codex/partials/as-of.blade.php` so the two read surfaces
  match.
- New "Referenced in scenes" section: the collection from `ReferencingScenes`, each row linking
  to the scene with its act and chapter, in the `x-table` family used by the codex list.
  First 20 rows, then a "show all N" toggle that reveals the rest in place (Alpine, no request).
- `show()` calls `CodexAttributeSheets::setOnly()` and `ReferencingScenes`.

Not in scope: pagination or a filtered scene list. Both sections load their full collection;
only the display is capped.

## Depends on

01, 02.

## Key decisions

- **No "current value".** The codex is not anchored to a moment. An attribute is a sequence of
  values, and only a scene gives one primacy — the as-of panel's job, not this page's. A design
  that surfaces one period as *the* value is wrong here.
- An attribute with no baseline and no periods does not appear at all.
- The cap is display-only and expands client-side. There is no page to link "see all" to:
  `SceneController::index` is book-scoped, filters on search and chapter, and does not
  paginate.
- `attribute-timeline.blade.php` is not reused — it carries the period editors.

## Consult

`expanded/ui.md` → the attribute partial and Scale.
`expanded/open-questions.md` → the first and third entries, which are binding.

## Tests

- An attribute with a baseline and two later periods renders all three, baseline first.
- An attribute the project defines but the entry never set is absent from the page.
- Referencing scenes render in the order `ReferencingScenes` returns.
- An entry referenced by more than 20 scenes renders the count and the toggle; all rows are
  present in the response.
- The section is absent when the entry is referenced by no scene.
