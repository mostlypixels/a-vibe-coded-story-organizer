# Open questions

The grill (2026-08-08) closed every question that shaped the plan. What remains is below;
everything else is recorded as settled in `plan/00-overview.md` → *Binding decisions*.

## Still open

1. **Does the Progress page need a cumulative view?**
   → **Not in this feature.** The climbing-toward-a-target line is what a challenge draws,
   and `WordCountSeries` already carries `total` alongside `written`, so adding it later is
   a dataset, not a redesign. Revisit when `word-count-challenges` is planned.

2. **What does the month `<select>` show for a writer whose history is shorter than 12 months?**
   → **All 12 anyway.** An empty month renders the empty state, which is a true answer.
   Filtering the list to months that have rows means the control changes shape as you use
   the app, which is worse.

3. **Should the daily goal apply to every day, including days the writer never intends to write?**
   → **Yes, flat.** A per-weekday goal ("1,000 on weekdays, 0 at weekends") is a real want
   and a real feature — a second spec, not a column here. The flat line is honest about
   what it is.

## Closed by the grill

| | Resolution |
| --- | --- |
| Where the chart lives | Its own **Progress** page under Tools ▾, not `projects/show` |
| Delta or cumulative on the chart | Delta, as **bars**, with the daily goal as a flat line over them |
| First snapshot's figure | Not a special case — **before a project's first row its total was 0** |
| Migration backfill | **Dropped.** No release, so nothing pre-exists; the demo generator covers seeds |
| Import baseline row | **Replaced** — snapshots travel in the export, so there is nothing to baseline |
| Goals on export/import | Both goals **and** snapshots travel |
| Monthly goal | **Moved to `word-count-challenges`.** A window with a target is a challenge |
| Demo history for the seeds | **Yes** — see [demo-history](demo-history.md) |
| Range switching | Full page reload, range in the URL. No JSON endpoint |
| Range cap | **366 days** |
| Timezone list | Full `DateTimeZone::listIdentifiers()`, Laravel's `timezone` rule |
| Pruning snapshots | **No.** ~365 rows per project per year |
| Readouts vs the range picker | Readouts always show **now**; only the chart moves |
| Dashboard | A real **Progress card** — two bars + a 14-day compact chart |
| Tools landing page | **In scope**, one card per tool, no live numbers |
