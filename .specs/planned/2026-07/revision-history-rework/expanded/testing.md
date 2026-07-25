# Testing — Revision History Rework

Plain PHPUnit, `RefreshDatabase`, factories, `actingAs($user)`, `route()` — never raw URLs
(`tests/Feature/ProjectTest.php` is the style reference). Tests run on in-memory SQLite in
parallel (`composer test`), so nothing may assume shared state or a fixed row count across
processes. JS gets vitest files co-located in `resources/js/`.

Existing suites that **must keep passing unchanged** (they encode the shipped contract):
`FieldAutosaveTest`, `RevisionRecorderTest`, `RevisionDataModelTest`, `RevertRevisionTest`,
`RevisionRetentionAndPurgeTest`, `AdminRevisionsPageTest`, `RevisionBrowserTest`,
`BackfillBaselineRevisionsMigrationTest`, `AutosavableFieldsAndHasRevisionsTest`.
`RevisionHistoryTest` is rewritten (its routes change) — the *behaviours* it asserts move
into the new tests below, none are dropped.

## Feature tests

### `tests/Feature/RevisionSaveGroupingTest.php` (new)

* Two fields saved by one form submit share a `save_id`; a second submit gets a different
  one. **This is the test that proves the `scoped()` container binding** — without it each
  field would get its own id.
* An autosave PATCH and a subsequent form submit produce different `save_id`s.
* A coalescing autosave (inside `config('revisions.windows')`) keeps the row's original
  `save_id` *and* refreshes its `summary_html` / `change_count`.
* A save touching two entities in one request (import) gives each entity its own `save_id`.
* `ensureBaseline()` writes a baseline row with its own `save_id` and null summary.
* Every row written through every entry point has a non-null `save_id` (a sweep assertion:
  `Revision::whereNull('save_id')->count()` is 0 after exercising each path).

### `tests/Feature/RevisionHistoryTest.php` (rewritten)

* Owner sees the entity history at `revisions.index`; **non-owner gets 403**.
* One row per save point, newest first, listing every field that save touched.
* `?field=` filters to one field; an unregistered `?field=` 404s.
* `?label=` search still filters.
* Pagination at `config('revisions.history.per_page')`; the last row of page 1 and the
  first row of page 2 both get a working *Compare with previous* link (the N+1 boundary).
* The response never hydrates `value` — assert via a query listener that no executed
  select against `revisions` includes the `value` column (the existing
  "list queries never hydrate value" invariant).
* Legacy `revisions.field` URL redirects to `revisions.index?field=…` (301/302 asserted).
* Baseline save point renders "Initial value", carries no Undo button.
* Current save point carries the **Current** badge and no Undo button.

### `tests/Feature/RevisionCompareTest.php` (new, replaces the compare half of the old test)

* Owner can compare two save points; **non-owner gets 403**.
* `?from=&to=` drives the page; unknown save ids 404; a malformed ULID 404s at the router.
* Reversed `from`/`to` in the URL renders the same diff as the correct order (the
  chronological guard) — never a backwards diff.
* Missing `from`/`to` defaults to the two most recent save points.
* **Snapshot semantics**: a field neither save point wrote, but which changed between
  them, appears as a changed section. A field that did not change is listed in the
  "unchanged" line, not rendered.
* `?field=` renders one section only.
* A field that did not exist at the older point renders as a whole-value insert.
* The right picker's options exclude everything not newer than the left selection
  (assert on the rendered option set, `disabled` attribute present).

### `tests/Feature/RevertSaveTest.php` (new)

* Owner reverts a whole save: every field it touched returns to its previous value, and
  *n* new `origin: revert` rows are written sharing **one new** `save_id`.
* **Non-owner gets 403**; an anonymous request is redirected to login.
* Base-hash mismatch on **any** field → 409, and **nothing** is written (assert the
  revision count and every field value are unchanged — the all-or-nothing guarantee).
* Redirect lands on the entity's edit form with the `reverted-save` flash naming the
  restored fields.
* Reverting a save twice in a row is legal and simply moves forward again (append-only).
* A revert whose old value no longer passes today's validation rules
  (`AutosavableFields::validationRule`) fails cleanly rather than storing it.
* Reverting the *current* save point is refused (no button, and the POST 422/409s).

### `tests/Feature/RevisionSummaryTest.php` (new)

* A recorded change stores a `summary_html` containing `<ins>`/`<del>` and a
  `change_count` matching the number of hunks.
* A find-and-replace producing 40 hunks stores a summary bounded by
  `config('revisions.summary.max_length')` characters of *text*, and `change_count = 40`;
  the row renders "and 39 more changes".
* Summary HTML is escaped: a value containing `<script>` or `&` round-trips as text, never
  as markup (assert on the stored column *and* the rendered page).
* Baseline rows store null / 0.

## Unit tests

### `tests/Unit/Services/HtmlTokenizerTest.php`

* Paragraphs, headings, list items, blockquotes (incl. `data-callout-type`), tables and
  images each become the expected block.
* Whitespace normalisation: `<p>a  b</p>` and `<p>a b</p>` tokenise identically.
* Mark stacks: nested `<strong><em>` yields the right signature; the same text with and
  without a mark yields different signatures.

### `tests/Unit/Services/VisualHtmlDifferTest.php`

* Word change inside a paragraph → inline `<ins>`/`<del>`, surrounding text untouched.
* Bold added with no wording change → block flagged *formatting changed* (and **not** the
  old "formatting changed only" dead end).
* Paragraph inserted / removed → whole-block insert / delete.
* Paragraph moved → reported as delete + insert (documented limitation, asserted so the
  behaviour is deliberate).
* Complexity cap: with `max_word_complexity` set to a tiny value in the test, a large
  replace degrades to block level instead of inline.
* **Security**: a stored value containing `<del>injected</del>` (impossible through the
  sanitizer, possible through an import) cannot produce a change marker in the output —
  the renderer's own allow-list wins.
* Output contains no tag outside `DiffHtmlRenderer::EMITTED_TAGS`.

### `tests/Unit/RevisionDifferRoutingTest.php`

* `FieldKind::Rich` routes to the visual differ; `Markdown`/`Plain` to the source diff.
* `Scene.contents` is never routed through the sanitizer or the HTML tokenizer (the
  architectural Markdown/Rich split).

### `tests/Unit/RichTextFieldsDiffTagsTest.php`

* `RichTextFields::ALLOWED_TAGS` contains `s` and **does not** contain `ins` or `del` —
  the guard that keeps the diff layer's markers unforgeable by an author. One assertion,
  high value: the whole diff rendering rests on it.

### `tests/Unit/RevisionSnapshotTest.php`

* "State as of save point" resolves each field to the newest revision at or before that
  moment, including fields the save did not write.
* Ties inside one second resolve by id, deterministically.
* A field with no revision at that point resolves to null.

## Migration test

### `tests/Feature/AddSaveGroupingMigrationTest.php` (new, mirrors `BackfillBaselineRevisionsMigrationTest`)

* Rows existing before the migration are **deleted** by it (the table is empty afterwards).
* The three columns exist with the expected types, and a row can be written carrying all
  three.
* `down()` drops the three columns cleanly, and `up()` can run again after it.
* After the migration, the first write to a field re-seeds a `baseline` row from the live
  value (`RevisionRecorder::ensureBaseline()`), so history restarts rather than staying
  empty.

## JS tests (vitest, `npm run test`)

### `resources/js/revision-picker.test.js`

* Arrow keys move the active option and update `aria-activedescendant`; Enter selects;
  Escape closes and restores focus to the trigger; Home/End jump.
* `aria-expanded` tracks the panel state.
* Typing filters the option list; the "manual only" toggle and the date range narrow it.
* Options not newer than the left selection are rendered `disabled` and are skipped by
  arrow navigation.
* Selecting an option navigates with the right query string.

## Import / export

Extend `tests/Unit/Import/*` and the export test that covers `include_revisions`:

* Exported sidecars carry `save_id`.
* Importing an archive **preserves grouping**: two source rows sharing a `save_id` share a
  (different, locally fresh) `save_id` after import; two source groups stay two groups.
* An archive with no `save_id` (pre-feature export) imports with one fresh id per row.
* Imported rows get recomputed summaries, in replay order.

## Manual verification (`/run-imagoldfish`)

Not a substitute for the above, but the diff is visual: after the diff task and after the
compare task, drive the app and screenshot (a) a rich field with a bold-only change,
(b) a scene with three fields changed in one save, (c) the picker open with filters
applied, (d) the same pages at 200% zoom and with keyboard-only navigation.
