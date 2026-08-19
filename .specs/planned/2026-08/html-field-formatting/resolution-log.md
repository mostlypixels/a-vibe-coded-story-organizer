# HTML field formatting — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **The palette is theme tokens, not a new palette.** The expanded spec proposed six hues
  copied from `PlotlineColors` with a `prefers-color-scheme` dark variant. The user
  proposed semantic colours instead. `ThemeTokens::PAIRS` already contrast-checks
  `danger|success|warning|info-surface-content` and `content-subtle` against every page
  surface in every preset, and every preset keeps the same hue — so the app side costs
  nothing and works in all four themes for free. Open questions 1 and 7 are closed by this.
- **The stored class is the colour name, not the token name.** `rt-color-red`, never
  `rt-color-danger`. A novelist marking a faction name thinks "red", not "danger"; the
  token is only where a contrast-safe red comes from.
- **Diff support is in scope**, against `open-questions.md` item 6, which recommended
  deferring it. The user chose to fix it now rather than ship a compare screen that reports
  "no change" for a real edit. It is task 06.
- **Justify is included**, per open question 2.

## Deviations from the spec/plan

- **`expanded/architecture.md` hangs the Markdown lock off `AuthorMarkdown::render()`**,
  not off `ContentSanitizer::assertMarkdownAllowed()` as first drafted. Cause: open
  question 5 turned out to be a live stored-XSS hole rather than a formatting gap, and was
  fixed separately (#117) before this feature. That fix created a single seam where all
  author-Markdown rendering is sanitized, which is a better place for the lock than the
  import check.

## Issues → resolutions

_None yet._
