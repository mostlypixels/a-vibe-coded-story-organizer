# Open questions

Each has a recommended answer. `mp-plan-tasks` grills this file first.

1. **Does the coloured line plot the daily delta or the cumulative total?**
   → **Delta.** The grey daily-goal line is a per-day number; the two cannot share a Y axis
   otherwise. The cumulative view is what a *challenge* wants
   (`word-count-challenges`), not a standing daily goal. If both are wanted here, it is a
   toggle over the same series — `WordCountSeries` already carries `total` and `written`.

2. **What is the first-ever snapshot's `written` figure?**
   → **0.** A project with 40,000 existing words would otherwise open with a 40,000-word
   day it never had. Accepted cost, and it belongs in `standing-issues.md` at ship time.

3. ~~**Do the Melusine demo projects get a synthetic history?**~~
   **Decided: yes** — see [demo-history](demo-history.md) for the generator, its determinism
   requirement, and why a fictional history is not a violation of "no history before you
   turn it on".

4. **Range switching: full page reload, or a JSON endpoint?**
   → **Reload.** No second authorized surface, no duplicate serialization, and the range
   stays in the URL. Revisit only if switching months feels slow in the browser check.

5. **Is `?from=&to=` on `projects.show` the right home, or does the chart deserve its own page?**
   → **Stay on `show`.** The spec says dashboard, and the readouts belong beside the header
   total. But `show` is already the heaviest page in the app — if the query budget in
   [testing](testing.md) fails, splitting is the fix, not caching.

6. ~~**Should a snapshot be recorded when a whole project is imported?**~~
   **Decided: yes, once, when the import finishes.** `ProjectImporter` writes in bulk and
   fires no events, so without it a freshly imported project has no history and question 2's
   rule swallows the entire manuscript. The call goes at the end of the import, after the
   scene word counts are settled — one `WordCountSnapshotRecorder::record()`, giving the
   project a day-one baseline that every later delta measures from.

7. **Cap on the range span — 366 days, or none?**
   → **366.** One point per day, hand-editable URL, and a `WordCountSeries` that fills gaps
   materialises every day in the range in PHP. A five-year request is a memory
   question, not a chart.

8. **`users.timezone` — free-text identifier or a curated shortlist?**
   → **Full `DateTimeZone::listIdentifiers()`**, validated by Laravel's `timezone` rule. A
   shortlist is a support burden the moment someone lives outside it.

9. **What happens to the goals when a project is duplicated or exported?**
   → Out of scope here, but the export/import format (`documentation/`, `ProjectImporter`)
   will need a decision. Recommend: **goals travel, snapshots do not** — a goal is intent,
   a snapshot is history that happened to another copy.

10. **Does anything need to prune old snapshots?**
    → **No.** One row per project per day is ~365 rows a year. `RevisionSetting`-style
    retention would be machinery for a table that will not reach five figures this decade.
