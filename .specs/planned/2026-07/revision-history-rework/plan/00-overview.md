# Revision History Rework — Plan overview

Source design: `../expanded/*.md` (overview, data-model, architecture, diffing, ui,
testing, open-questions) and the brainstorming in `../notes/`. Where `notes/` and
`expanded/` disagree, **`expanded/` wins** — it is the later, grilled version, and it
records each deviation and why (e.g. clearing legacy rows, entity-level compare).

This overview is the manual for the 19 task files below. It is never itself implemented
or moved to `plan/implemented/`.

## The one-sentence shape of the feature

Storage stays **per field** (immutable, append-only rows in `revisions`); everything above
storage moves to **per entity**, addressed by *save point* (`save_id`) — history lists save
points, compare diffs two of them across every field, revert undoes one, and a single
field becomes a `?field=` filter rather than a parallel set of pages.

## Execution order

| # | Task | Purpose |
|---|---|---|
| **Phase A — capture** | *time-sensitive: every day without it is ungroupable history* | |
| 1 | `01-save-grouping-migration.md` | Migration: delete legacy rows, add `save_id`, `summary_html`, `change_count` + indexes |
| 2 | `02-recorder-stamps-save-id.md` | `RevisionRecorder` stamps `save_id`; `scoped()` binding; coalescing keeps the original |
| 3 | `03-export-import-save-grouping.md` | Export carries `save_id`; import remaps groups to fresh local ids |
| **Phase B — the diff engine** | *pure logic, no UI, unit-testable in isolation* | |
| 4 | `04-html-tokenizer.md` | `HtmlBlock` + `HtmlTokenizer`: purified HTML → blocks, inline tokens, mark signatures |
| 5 | `05-visual-html-differ.md` | `VisualHtmlDiffer`: block diff → inline diff, with the complexity cap |
| 6 | `06-diff-html-renderer.md` | `DiffHtmlRenderer`: structured diff → safe HTML, own tag allow-list |
| 7 | `07-differ-routing-and-cleanup.md` | `RevisionDiffer` routes Rich→visual, Markdown/Plain→source; delete `formattingChangedOnly` |
| **Phase C — summaries at write time** | | |
| 8 | `08-revision-summarizer.md` | `RevisionSummarizer`: first hunk + context, bounded by rendered length; hunk count |
| 9 | `09-recorder-writes-summaries.md` | Recorder stores summaries on insert *and* on coalescing update; import recomputes |
| **Phase D — the read model** | *services only, still no routes* | |
| 10 | `10-save-point-history-service.md` | `SavePoint`/`SaveEntry` + `RevisionHistory`: grouped, paginated, filtered |
| 11 | `11-snapshot-and-comparison-services.md` | `RevisionSnapshot` (state as of a save point) + `RevisionComparison` |
| **Phase E — the screens** | | |
| 12 | `12-diff-blade-component.md` | `<x-diff>`: tint + gutter mark + sr-only text, inline (summary) and full modes |
| 13 | `13-entity-history-page.md` | `revisions.index` route/controller/view; field + label + manual-only filters; legacy redirect |
| 14 | `14-entity-compare-page.md` | `revisions.compare` route/controller/view; one section per changed field; **no-JS `<select>` baseline** |
| 15 | `15-revision-picker-combobox.md` | `x-revision-picker` + Alpine: APG combobox progressively enhancing task 14's selects |
| **Phase F — revert** | | |
| 16 | `16-revision-reverter-service.md` | Extract `RevisionReverter::revertField()`; conflict → redirect-with-error |
| 17 | `17-whole-save-revert.md` | `revisions.saves.revert`: all-or-nothing, one transaction, one new save point |
| **Phase G — wiring & docs** | | |
| 18 | `18-entry-points-and-navigation.md` | Edit-page History link, sidebar retargeting, flash messages |
| 19 | `19-docs-and-changelog.md` | `documentation/architecture.md` + `glossary.md` + `CHANGELOG.md` |

Ordering is by dependency, not narrative. Phase B is deliberately buildable and verifiable
without a single route: the diff engine is the riskiest part, and it should be proven by
unit tests before any page depends on it.

## Binding decisions (do not re-litigate)

Settled in the expanded docs and the grilling pass. Every task must honour them.

1. **The interface unit is the entity, not the field.** History, compare and revert all
   address a *save point*. `?field=` is a filter. The two legacy field-scoped URLs survive
   only as redirects. (User decision, mid-session.)
2. **Storage does not change.** Per-field rows, immutable, append-only. Revert writes new
   rows; nothing rewrites history; the only permitted `UPDATE` is the existing coalescing
   overwrite (now also refreshing that row's summary columns).
3. **A coalescing autosave keeps its row's original `save_id`** (and its original
   `created_at`). Accepted consequence: a save whose field coalesces into an earlier burst
   lands in that earlier save point. Documented, not worked around.
4. **Legacy revision rows are deleted by the migration** (owner's call). Every row in the
   table therefore came from the new write path: no null `save_id` branch, no summary-less
   era, no non-portable `COALESCE`+concat. History restarts from a `baseline` row per
   field, re-seeded on that field's next write. Safe to do because the project is pre-V1
   and the only existing data is the Melusine demo seed; one `Removed` changelog line.
5. **Compare compares *snapshots*.** A save point resolves, per field, to the newest
   revision at or before that moment — so a field neither save wrote can still appear as
   changed. That is correct and must not be "fixed".
6. **The visual differ is in-house**, on `jfcherng/php-sequence-matcher` (BSD-3, already
   in `vendor/`). No new composer dependency. `caxy/php-htmldiff` is rejected on licence
   (GPL-2.0 in a source-shipped app), the alternatives on maintenance. (Grill decision.)
7. **Diff markup is produced, never sanitised.** Order is: already-purified content in →
   diff → wrap → render. The renderer escapes every text node and emits only its own
   allow-list. **Never purify after wrapping.** `<ins>`/`<del>` belong to the diff layer;
   the editor's strikethrough stays `<s>`.
8. **Compute at write, never at read.** A diff between two immutable revisions is a
   constant: list summaries are stored columns. No page computes a diff to render a list.
9. **"Undo this save" restores only the fields that save touched** — never a whole-entity
   rollback. (Grill decision.)
10. **A base-hash conflict redirects back with an error alert**, for both revert paths.
    The 409 *status* survives only on the JSON autosave endpoint. (Grill decision.)
11. **The history page gets a "manual saves only" filter**, sharing the server-side filter
    logic with the compare pickers. (Grill decision.)
12. **No new dependency, no new pattern.** Services in `app/Services`, value objects in
    `app/Support`, config in `config/revisions.php`, Alpine + vitest for JS.

## Invariants every task must preserve

* **Authorization walks to the `Project`.** Reads authorize `view`, writes `update`, via
  `HasRevisions::revisionProject()`. Every new route ships a non-owner-403 test.
* **List queries never hydrate `revisions.value`.** `size_bytes` and `summary_html` exist
  precisely so they don't have to.
* **`project_id` is always set explicitly**, never inferred from the polymorphic pair.
* **Portable SQL**: no window functions, no engine-specific string concatenation, no
  `LENGTH()` variants — sqlite/mysql/mariadb/pgsql/sqlsrv all run the same plan.
* **The Markdown/Rich split is architectural**: `Scene.contents` never touches the
  sanitizer or the HTML tokenizer.
* **Every configurable number lives in `config/revisions.php`** — never a literal in a
  service or a view.
* **Tests run in parallel on in-memory SQLite**: no shared state, no absolute row counts
  across processes.
