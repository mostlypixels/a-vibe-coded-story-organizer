# UI

## Page shape

Five new `resources/views/<entity>/show.blade.php`, each modelled on
`resources/views/codex/show.blade.php`:

- `<x-app-layout>`, then a header row: title block on the left, action icons on the right
  in a `flex shrink-0 items-center gap-1`.
- Header actions, in this order: `x-icon-edit-link`, `x-icon-button icon="history"`
  (`revisions.index` with the `AutosavableFields::REGISTRY` slug — `act`, `chapter`,
  `plotline`, `event`, `scene`), then `x-icon-delete-button`. Scene also gets the
  duplicate dialog it has today (`scenes.duplicate`); act and chapter get their
  delete-with-move dialog instead of a plain delete.
- Body: `<div class="space-y-6">` of `x-card`s, each rendered only when it has content —
  `@if (filled($x->description))`, `@if ($rows->isNotEmpty())`.
- Rich text through `x-rich-text`; dates through `x-date`; child lists through
  `x-table` / `x-table-row` / `x-table-cell` with `:striped="$loop->even"`.

## Scene prose

`resources/views/shared/scenes/show.blade.php` renders `{!! $scene->renderedContents !!}`
inside a `prose prose-sm ... text-justify [&_p]:my-4` article. Extract that article into
`resources/views/components/scene-prose.blade.php` and call it from both. Two callers, so
the component is earned.

The prose is the scene. It renders in full on `scenes.show`, not behind a toggle.

## Index rows

Each of the five index views changes in two places:

- The name cell `<a href>` moves from `*.edit` to `*.show`.
- The action cell gains `<x-icon-view-link :href="route('<entity>.show', $model)" />` as
  the **first** icon, before `x-icon-edit-link`.

Concrete lines today: `plotlines/index.blade.php:25,38`; `acts/index.blade.php:29,44`;
`chapters/index.blade.php:38,54`; `scenes/index.blade.php:40,65`;
`events/index.blade.php:35,58`.

## Not on a read page

No `<form>`, no `<input>`, no autosave wiring, no Alpine that writes. The only Alpine
allowed is disclosure — the `showAll` toggle `codex/show.blade.php` uses to cap a long
child list at twenty rows. Apply that same cap to the scene lists on the act, chapter and
event pages.

## Empty entity

A plotline with no events, an act with no chapters: render the header and nothing else.
No empty-state card per section — `codex.show` omits sections rather than filling them.
