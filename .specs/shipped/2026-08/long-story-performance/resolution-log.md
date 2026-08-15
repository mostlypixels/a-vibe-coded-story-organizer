# Long Story Performance — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **Markdown-render caching deferred to a follow-up spec** (`scene-render-cache`).
  `chapter` mode makes the overview fast without it; caching's remaining value is
  `book` mode on huge stories and EPUB export — a separable concern.
- **Act header always shown** on a `chapter`-mode page (current chapter's act), so a
  mid-act chapter page keeps the reader's place. Not "only above the act's first chapter".
- **Mode switch: overview header, owner-only.** Not the project-edit page; a view-only
  user sees the owner's mode with no control.
- **Chapter addressed by id** in `?chapter={id}` — stable across reorder, drives the auth
  walk. Not the project-wide number.
- **Setting is a column on `projects`**, not a `StorySetting` model — one enum
  view-preference is a project attribute.
- **03 split into 03 (render one chapter) + 04 (navigation between chapters)** so each is
  feature-testable on rendered response content.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

_None yet._
