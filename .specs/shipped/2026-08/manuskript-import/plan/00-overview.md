# Manuskript Import — plan overview

Branch-only feature (`manuskript-import`), never merged to `master`. No routes, no policies, no UI,
no `documentation/` page — the design docs in `../expanded/` are the documentation.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `manuskript-file-parser` | `ManuskriptFile`: the one header+body format every source file shares. |
| 02 | `test-fixture` | The trimmed source tree under `tests/Fixtures/manuskript/` every later test reads. |
| 03 | `command-and-project` | The artisan command, its gate, user resolution, the project + single act, the report. |
| 04 | `chapters-and-scenes` | Chapters, scenes, ordering, the Markdown gate. |
| 05 | `characters` | `characters/*.txt` → codex entries of type `character`. |
| 06 | `real-tree-smoke-run` | Import the real source tree, verify it in the browser. |

## Binding decisions

Settled in the spec docs and the grill; no task re-opens them.

* Scene `contents` stays **Markdown** — no HTML conversion. The import writes **no revisions**.
* One synthetic act, `"Act 1"`, overridable with `--act=`.
* `position` is the 1-based index in the numerically-sorted list, never the source's `NN-` prefix.
* Scene status is the column default (`draft`). Manuskript's `status.txt` is never read.
* Disallowed rendered HTML in a scene body is **stripped**, replaced by `[INVALID CONTENT REMOVED]`
  on its own line, and counted. It never aborts the import.
* Character fields: `Name` → entry name; `ID`, `Color`, `Importance`, `POV` skipped; fields whose
  trimmed value is `''` or `?` skipped; the rest become `<h3>Key</h3>` + one `<p>` per blank-line
  block (single newlines → `<br>`).
* Empty chapters and empty scenes are imported and counted, never dropped.
* Ignored and counted, never guessed at: files loose under `outline/`, `world.opml`, `plots.xml`,
  `labels.txt`, `status.txt`, `settings.txt`, and every scene header key other than `title:`.
* One `DB::transaction()` around the whole import; nothing is resumable and nothing is idempotent —
  a second run makes a second project.

## Invariants every task must preserve

* `position` contiguous from 1 within each sibling set (acts in project, chapters in act, scenes in
  chapter) — the app's ordering invariant, and the reason source prefixes are renumbered.
* `Project::booted()` already creates the main plotline and the Start/End fixed events. Never create
  or touch them.
* Scene writes must go through `$model->save()`, never a bulk `insert()` — `Scene::booted()`'s
  `saving`/`saved` hooks own `word_count` and the word-count snapshots.
* `CodexEntry.description` is a rich-HTML field: everything written into it must survive
  `RichTextFields::ALLOWED_TAGS` unchanged, so raw values are `e()`-escaped first.
* Console runs bypass `NormalizeLineEndings` middleware — the importer normalizes CRLF → LF itself.
