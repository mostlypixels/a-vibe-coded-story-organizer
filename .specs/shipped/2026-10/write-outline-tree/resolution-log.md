# Write outline tree — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- 2026-10-20 grill: current book only; hide the act heading for one act; acts not foldable; chapter word totals, scene rows name only; recent section closed by default; chapter fold state not saved.
- Recent scenes go in a folding section (user request), styled like the right-panel sections.
- No number search (user request).
- After shipping (user request): Recent and Outline are each one card, styled like the right-panel cards. Chapter word totals removed.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

- `assets-state.sh` fails while the Docker dev stack runs: `public/hot` is its Vite server and the native `database.sqlite` is behind migrations. Not caused by the task. Checked the page in Docker instead.
- Final browser check (Gardenia Girl): chapters named "Chapter N" render as "Chapter 1 — Chapter 1", and the word total truncates the label in a 280 px panel. Left open for the user to decide.
