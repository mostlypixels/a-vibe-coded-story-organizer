# Open questions

- **Ship monthly recurrence in the same pass, or fixed windows first?** — Recommend the same
  pass. The whole cost is `ChallengeWindow` plus one enum column; deferring means shipping a
  feature that still cannot express the monthly goal `word-count-goals` deleted.
- **Should a monthly challenge's first month be pro-rated to `starts_on`?** — Recommend no.
  Clipping needs a rule on the page ("this month's target is 12,900 because you started on
  the 10th") to explain a number the writer never typed.
- **`monthly` only, or weekly and yearly too?** — Recommend monthly only. The enum makes the
  others cheap later; nothing in the spec asks for them, and each adds a window rule and its
  tests.
- **Does the dashboard Progress card surface the nearest-deadline challenge?** — Recommend
  no for now. The dashboard is already the heaviest page and is queued for a pre-v1
  clean-up; the Progress page is one click away.
- **Is 366 days the right cap on a fixed window?** — It is the shipped chart cap, reused for
  the same in-memory reason. But "a trilogy by 2029" is a plausible challenge, and the cap
  refuses it. Recommend keeping 366 and revisiting when someone asks.
- **A challenge with no chart data at all (window entirely in the future).** Recommend the
  card shows window, target and derived par-per-day, and simply omits the canvas — an empty
  chart claims the writer wrote nothing.
- **Does an edit to a running challenge deserve any trace?** — Recommend none. No revisions,
  no "target changed on the 14th" note; the spec calls a finished challenge a record, and
  `word-count-goals` already accepted that changing a goal re-draws the past.
- **`x-word-count-chart` reuse.** The source spec says reuse the shipped chart; [ui](ui.md)
  adds a second component in the same JS module instead, because the datasets and the axis
  model differ. Confirm that reading of "reuse".
- **Should a met challenge stop counting?** A writer who passes 50,000 on the 20th keeps
  writing. Recommend the bar caps at 100% (`x-progress-bar` already does) while `written`
  keeps rising in the text.
