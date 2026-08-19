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
- **The slash menu has no "Align left" entry.** `ALIGNMENTS` holds only the three names
  that write a class, and the menu offers one entry per registry value. The `setTextAlign`
  command still accepts `left` to clear the class — task 05's toolbar needs that reset,
  and must not add `left` to the registry to get it.

## Deviations from the spec/plan

- **`expanded/architecture.md` hangs the Markdown lock off `AuthorMarkdown::render()`**,
  not off `ContentSanitizer::assertMarkdownAllowed()` as first drafted. Cause: open
  question 5 turned out to be a live stored-XSS hole rather than a formatting gap, and was
  fixed separately (#117) before this feature. That fix created a single seam where all
  author-Markdown rendering is sanitized, which is a better place for the lock than the
  import check.

- **`expanded/architecture.md` and `expanded/ui.md` carried the pre-grill palette** —
  six hues from `PlotlineColors` with hand-written `prefers-color-scheme` values, and a
  `--rt-color-*` custom-property layer. Cause: the grill replaced the palette after the
  expansion was written, and only `plan/00-overview.md` was updated. Caught after task 01
  because task 02 consults `ui.md` → *Palette definition* directly and would have built
  the wrong thing. Both documents were corrected mid-run to the binding five and to the
  theme-token mapping. No code was written against the stale version.

## Issues → resolutions

- **An alignment-only edit first came out as "no change".** Root cause: task 06 scopes
  alignment as a block attribute only, and `VisualHtmlDiffer::appendMatched()` decides
  "unchanged" on the signature alone — so a re-aligned paragraph paired with its old self
  and reported nothing, which is the exact failure the task exists to prevent. Fix: the
  block's alignment now leads its signature (`HtmlTokenizer::signatureOf()`), and
  `VisualHtmlDiffer::formattingOf()` reports it as the pseudo-mark `align:center` beside the
  real marks, so the badge names it. `matchKey()` is untouched, which is what keeps the
  pairing intact — a test asserts both halves. A green suite did not catch this: every test
  written for the attribute path passed.

- **Saving a rich field can 500 with `SQLSTATE[HY000]: database is locked`.** Seen twice
  while driving the browser. **Not this feature** — the same error is in
  `storage/logs/laravel.log` from 2026-08-08 and 2026-08-12. It is dev-only SQLite
  contention between the autosave POST and an explicit form save; the write itself is
  correct (the logged SQL carries both classes). Retry the save. Do not chase it from
  inside this feature.
