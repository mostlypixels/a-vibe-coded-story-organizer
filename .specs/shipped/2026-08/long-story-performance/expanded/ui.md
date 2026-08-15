# UI

Reuse the existing `story.index` markup; the change is *how much* it renders and
two new controls. Prefer splitting the per-chapter block into a partial
(`story._chapter` or an `x-story-chapter` component) shared by both modes, so the
chapter render is defined once and `chapter` mode loops over one.

## Mode switch

A small control in the overview header (beside `x-word-count`), PATCHing
`projects.story.overview.mode`. Two choices: "One chapter per page" / "Entire
book". Owner-only; render only when the user can update the project.

- Discoverable where the performance is felt. A segmented control or a `<select>`
  that submits on change — keep presentation logic out of Blade.

## Chapter mode

- **Pager**: prev / next chapter links at top and bottom of the content column,
  labelled with the neighbour's number + name, disabled at the ends. Build from
  the neighbour ids the controller passes.
- **TOC** (left column): unchanged — whole book. Its chapter links become
  `?chapter={id}` (same-project), still carrying `#chapter-{id}` so the target
  scrolls into view (`scroll-mt-16` already set). Act links point at the first
  chapter of the act.
- Header still shows the book/act word totals from the aggregate; the current
  chapter card shows its own total as today.

## Book mode

Identical to today's `story.index`. No pager, TOC anchors stay same-page (`#...`
without the query param, or with it harmlessly).

## Accessibility

- Pager is real `<a>` links (keyboard-navigable), disabled state at ends.
- Mode control is a labelled form control; submitting on change must not trap
  focus.
