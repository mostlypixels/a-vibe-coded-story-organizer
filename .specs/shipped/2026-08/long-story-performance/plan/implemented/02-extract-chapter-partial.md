# 02 — Extract chapter partial

## Scope

Refactor only. Pull the per-chapter block of `resources/views/story/index.blade.php`
(the `x-collapsible-card` with its chapter heading, chapter word-count, and the
scene loop) into a single reusable render unit — an `x-story-chapter` component
or a `story._chapter` partial — and have `story.index` loop it.

- `book` mode output must be **byte-for-byte unchanged** (same DOM, ids, Alpine
  `x-data`, move buttons, scene numbering via the passed `$numbering`).
- The partial takes the chapter, `$numbering`, and whatever the scene rows need
  (route bindings unchanged).

Does **not**: add `chapter` mode, the act header variant, or any controller
change. The act-header-per-page treatment is task 03; this task leaves the act
bar exactly where it is today.

## Depends on

01 (in `implemented/`) — though this task reads no column, it lands on top of it.

## Key decisions

- Reuse `x-collapsible-card`, `x-word-count`, `x-icon-button`, the scene section
  markup as-is. This is extraction, not redesign.

## Consult

`../expanded/ui.md` → "Reuse the existing markup"; current
`resources/views/story/index.blade.php`.

## Tests

- Existing `tests/Feature/StoryTest.php` stays green (parity).
- If any assertion pins the old inline structure, update it to the partial while
  preserving the asserted output — do not weaken coverage.
