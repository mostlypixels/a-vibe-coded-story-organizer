# Tiptap Toolbar Format — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

- The grill step (`plan-tasks` skill step 2) was explicitly skipped at the user's request for this
  feature. Decomposition proceeded directly from the `expanded/` docs written by
  `mp-spec-expander`, with no user stress-test of the design beyond what's already recorded in
  `expanded/open-questions.md`.

## Deviations from the spec/plan

- Task 02: array names were renamed/reshaped slightly from what `expanded/ui.md`'s sketch
  showed and from what task 01 had actually landed (`$toggles` kept as one merged array,
  `$tableRowColumnOps`/`$mergeSplitOps` kept separate). Task 02 split `$toggles` into
  `$textFormat` (Bold/Italic/Underline/Strike) and `$listsAndBlocks` (the remaining six), and
  merged `$tableRowColumnOps` + conditionally-appended `$mergeSplitOps` into one
  `$tableStructure` array — matching the task file's naming exactly, since it names these
  arrays explicitly. No behavior change, purely the variable-shape cleanup the task called for.
- The dividers ended up placed **only between clusters** (4 dividers total: Headings|Text
  format, Text format|Lists & blocks, Lists & blocks|Insert, Insert|Table structure) — no
  leading divider before the first cluster or trailing divider after the last, matching the
  toolbar's pre-existing convention (never had edge dividers) even though the task's dropdown
  clusters are new. Read "including before/after each dropdown trigger" as "don't skip the
  normal between-cluster divider just because a neighbor is a dropdown," not as "add extra
  edge dividers."
- Table structure trigger glyph: used `&#9638;&#9998;` (square + pencil), the task's own
  suggested example, rather than inventing an alternative — it's visually distinct from
  cluster 4's plain `&#9638;` Insert-table glyph.

## Issues → resolutions

- **Issue**: a first-pass manual verification (`run-imagoldfish`) of the Headings dropdown
  looked empty/invisible in a full-page screenshot immediately after `click` with no
  `wait-for` before the `screenshot` step — worried this was a real overflow/z-index/clipping
  bug caused by the wysiwyg editor's outer `overflow-hidden` wrapper (which does contain the
  toolbar and its dropdowns).
  **Root cause**: none — it was a screenshot-timing artifact (screenshot taken essentially in
  the same tick as the click, before Alpine's `x-show`/transition had painted) plus the popover
  content being small/light against a light page background at the full-page screenshot's
  scale. Cropping the exact dropdown-content element (via a temporary `id` set through `eval`
  and `screenshot-element`) after a `wait-for` on one of its buttons showed the H1/H2/H3/H4 row
  and the 6-button table-structure row rendering correctly, opaque, and unclipped. **Lesson for
  future verification**: always `wait-for` a selector inside newly-opened dropdown/popover
  content before screenshotting it — a bare `click` immediately followed by `screenshot` is not
  reliable evidence either way.
- Verified live in the browser (`run-imagoldfish`, logged in as the seeded dev user, on
  `projects/1/edit`'s Description field, HTML mode): Headings dropdown opens, selecting
  "Heading 2" both applies the heading and updates the trigger button's label to "H2" and its
  `:class` to the active/highlighted state (confirmed via `outerHTML` inspection — the
  `x-text` nested-ternary and the `isOn(...) || isOn(...) || ...` active expression both
  evaluate correctly from real Alpine state, not just template markup). Table structure
  dropdown opens and shows all 6 buttons (row/col ops + merge/split) in HTML mode. No
  console errors during any of this.
