# 13 — Documentation

## Scope

- `documentation/word-count-goals.md` — the deep dive. What it must carry, because the code
  cannot say it:
  - the writer's day, and why the timezone is the **owner's**;
  - snapshots are cumulative, deltas are derived and never stored;
  - **before a project's first row its total was 0** — the rule that replaced every baseline
    mechanism;
  - what does not record (bulk writes, imports) and why that is by design;
  - why the chart uses bars and the status strip ignores the range picker.
- `documentation/word-count.md` — a *History and goals* section linking to it.
- `documentation/architecture.md` — a compact entry linking both, per the
  "entry point short, deep dive linked" rule.
- `CHANGELOG.md` — handled by `/ship-pr`, not here.

## Depends on

Everything. Last task.

## Key decisions

- Follow `.claude/rules/documentation.md` → *Verbosity*: lists by default, prose only for a
  *why*, no padding, no "skim this" annotations.
- **Do not restate the code.** Name the class and move on.
- Do not cite `plan/*.md` or a handoff from documentation or code comments — they are scratch
  and get moved or deleted (`.specs/README.md`).
- If anything in the feature ended up as an accepted cost, it belongs in
  `standing-issues.md`, not in `documentation/`.

## Tests

None of its own. Confirm `composer test` and `composer lint` are green, and drive the feature
in a browser with `/run-imagoldfish` — set goals, save a scene, watch today's bar move, switch
the range, and confirm the chart follows a theme change. A chart rendering in the wrong
palette passes every automated test in this plan.
