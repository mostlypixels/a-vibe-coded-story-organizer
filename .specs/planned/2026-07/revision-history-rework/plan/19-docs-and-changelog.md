# Task 19 — Documentation and changelog

## Scope

**`documentation/architecture.md` → *Revisions (autosave + field history)*** — rework, not
append. The section currently describes a per-field UI that no longer exists. It must now
explain, for a junior developer:

* the **two altitudes**: storage is per field (unchanged, immutable, append-only); the
  interface is per entity, addressed by *save point* (`save_id`);
* what a **snapshot** is, and why a field neither save wrote can appear in a comparison
  (with a `> [!NOTE]`);
* the **coalescing / `save_id` interaction** and its accepted consequence
  (`> [!WARNING]`), plus why the row keeps its original id;
* **compute-at-write**: `summary_html` / `change_count`, why lists never diff, and the
  accepted staleness after a prune (`> [!WARNING]`);
* the **diff pipeline**: `RevisionDiffer` routes by `FieldKind`; the in-house
  `HtmlTokenizer` → `VisualHtmlDiffer` → `DiffHtmlRenderer` chain, why it is in-house
  (licence + maintenance, name the rejected candidates), and the sanitisation order with
  the "never purify after wrapping" rule as a `> [!WARNING]`;
* why `<del>`/`<ins>` are reserved and the editor's strikethrough stays `<s>`;
* the **routes table** (entity history, compare, the two legacy redirects, both revert
  endpoints) and the `view`/`update` split;
* rename the heading to *Revisions (autosave + entity history)* and fix every stale
  reference to per-field pages elsewhere in the file.

**`documentation/glossary.md`** — add the terms this feature made load-bearing, drawn from
`notes/revision-ui-lexicon.md`: *save point / correlation id*, *snapshot*, *source diff vs
visual diff*, *hunk*, *combobox (vs select-only listbox)*, *compute-at-write*, *boundary
row*. Short entries with a pointer to the architecture section, not a copy of the lexicon.

**`documentation/best-practices.md`** — one short entry: derived, precomputed columns
(when to store a summary rather than compute it, and the backfill cost that buys).

**`CHANGELOG.md`** — a dated `## YYYY-MM-DD — Revision history rework (#PR)` section at the
top, below `[Unreleased]`, grouped `Added` / `Changed` / `Removed`. Call out explicitly:
the new entity-level URLs and the redirects from the old ones, the whole-save undo, the
visual diff for rich fields, the conflict-UX change (409 page → redirect with an error),
and the removal of "formatting changed only".

Include one `Removed` line for the migration clearing the `revisions` table (pre-V1, the
only data affected is the Melusine demo seed — a factual note, not a warning banner), and
one sentence in `documentation/architecture.md` saying the same, so someone asking "why is
my history empty after the upgrade?" finds the answer.

**`resolution-log.md`** — consolidate: the four grill decisions, the deviation from the
handoff note on legacy `save_id` backfill, and anything the implementers appended.

## Depends on

Tasks 1–18.

## Key decisions already made

* Documentation explains **why**, not only what, and uses GFM alert callouts for the
  pitfalls (CLAUDE.md). The three warnings above are the ones a junior will otherwise trip
  over.
* The changelog is per feature/PR, not per commit.

## Consult

* `documentation/architecture.md` — the section being reworked (and its current level of
  detail, which is the bar).
* All of `expanded/`, and `notes/revision-ui-lexicon.md` for the glossary wording.

## Tests

No code tests. Verification is:

* `composer test` and `npm run test` green;
* `composer lint -- --test` clean;
* every route name mentioned in the docs resolves (`php artisan route:list`);
* `tests/Unit/SpecsStatusConsistencyTest` still passes after `ship-plan` moves the folder.
