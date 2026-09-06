# 01 — `x-scene-prose` component

## Scope

- New `resources/views/components/scene-prose.blade.php`: the `<article class="prose
  prose-sm ...">` block that renders `{!! $scene->renderedContents !!}`.
- Repoint the two callers that exist today:
  - `resources/views/shared/scenes/show.blade.php`
  - `resources/views/components/story-chapter.blade.php` (the `x-show="open"` panel)
- Not in scope: the scene read page (task 06), which becomes the third caller.

## Depends on

Nothing.

## Key decisions

- The two callers' classes differ: `story-chapter` adds `text-[0.8125rem]`. Take a size
  prop or let the caller pass `class` through `$attributes` — do not fork the component.
- The shared public page keeps its own layout and controls. Only this block moves.

## Consult

`expanded/ui.md` → Scene prose.

## Tests

- `tests/Feature/SceneShareTest.php`: the shared page still renders the prose.
- The story overview still renders scene prose in its expanded panel.
