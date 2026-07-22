# Autosave With Revisions — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

_None yet._

## Deviations from the spec/plan

* Task 1: added `RevisionFactory` (not mentioned in the task file) so the
  required prunable() tests could seed rows the way this codebase's other
  model tests do (factories, not raw `Revision::create()`). Defaults to
  revisioning a fresh `Project`'s `description` field; tests override
  `revisionable_type`/`revisionable_id`/`project_id`/`field` as needed.
* Migration filename uses today's date (`2026_07_22_000000_create_revisions_table.php`)
  rather than the `2026_07_XX` placeholder in `data-model.md`, following this
  repo's existing migration-naming convention (sequential dated files).
* Task 2: migration filename likewise dated
  `2026_07_22_000001_widen_long_text_columns_to_long_text.php` (sequenced after
  task 1's same-day migration) rather than the `2026_07_XX_000001` placeholder
  in `data-model.md`.
* Task 2: `expanded/data-model.md`'s prose says widening `text()` → `longText()`
  "requires `doctrine/dbal`", but `00-overview.md`'s binding decision (confirmed
  during grilling) says the opposite and takes precedence. Confirmed again here:
  `composer.json`/`vendor/doctrine` has no `dbal` package, and
  `Schema::table(...)->longText('x')->nullable()->change()` ran cleanly against
  sqlite (the test DB) with no missing-dependency error — Laravel 13's schema
  builder performs the type change natively. No package was added.

## Issues → resolutions

* `Revision::prunable()` cannot be queried as `Revision::query()->prunable()`
  — `prunable()` is an **instance** method that `Illuminate\Database\Eloquent\
  MassPrunable` calls internally (via the scheduled `model:prune` command), not
  a local query-builder macro. Tests must call it as `(new Revision())
  ->prunable()` to get the `Builder` it returns. `data-model.md`'s code sample
  doesn't show test usage, so this only surfaced when writing task 1's tests —
  future tasks that need to run the prunable query directly (e.g. task 12's
  purge preview) should do the same.
* Laravel Pint's `fully_qualified_strict_types` fixer rewrote the `Revision`
  docblock's `{@see \App\Services\RevisionRecorder}` reference into a real
  `use App\Services\RevisionRecorder;` import — a class that doesn't exist
  until task 4. It doesn't break anything (PHP never resolves an unused `use`
  import), but it reads as a phantom dependency, so the docblock was reworded
  to reference `App\Services\RevisionRecorder` as plain text instead of a
  `@see` tag. Worth remembering for any other task that `@see`s a
  not-yet-built class in a docblock.
* Task 3: same Pint `fully_qualified_strict_types` fixer behavior recurred —
  `{@see \App\Support\AutosavableFields}` docblock references in both
  `FieldKind` and `HasRevisions` were rewritten into real `use
  App\Support\AutosavableFields;` imports (harmless, since `AutosavableFields`
  does exist by task 3, but Pint also silently dropped the `\` and shortened
  the `@see` tag body to the bare class name — left as Pint produced it, run
  again with `composer lint -- --test` to confirm idempotent/clean). No code
  change needed, just noting the pattern for any later task's `@see` tags.
* Task 3: PHPUnit's `@dataProvider` docblock annotation (as shown in some
  older Laravel docs/spec prose) is not supported by this project's PHPUnit
  version — it must be the `#[DataProvider('methodName')]` attribute
  (`PHPUnit\Framework\Attributes\DataProvider`), otherwise the test errors
  with `ArgumentCountError` (0 args passed, N expected) since the annotation
  is silently ignored. Confirmed via `AutosavableFieldsAndHasRevisionsTest`'s
  14-field-table data provider.
* Task 4: `architecture.md`'s `RevisionRecorder` sketch calls
  `AutosavableFields::windowSeconds(...)` with an elided argument list, but
  the real method (task 3) takes a URL *slug*, not a model class —
  `RevisionRecorder` only ever has a `Model $entity`. Added
  `AutosavableFields::slugFor(string $modelClass): string` (a small reverse
  lookup over `REGISTRY`) rather than duplicating the "Model.field" config-key
  derivation inside `RevisionRecorder` — keeps the slug/config lookup logic
  in exactly one place, per CLAUDE.md's "Configuration should be kept in a
  single place" rule. Not mentioned in the task file; a necessary extension
  of task 3's registry, not a redesign of it.
* Task 5: `down()` was written to delete `origin: baseline` revisions rather
  than being a no-op — the task file left this as an implementation choice to
  document. Chosen because leaving baseline rows behind on rollback would make
  a subsequent `up()` diverge from a fresh install's history: `ensureBaseline()`'s
  idempotent no-op check would then skip rows a fresh install would still seed.
  Safe either way `ensureBaseline()` seeded them (this migration or the live
  write path), since both routes produce identical rows.
* Task 5: migration filename uses `2026_07_22_000002_backfill_baseline_revisions.php`
  (dated/sequenced after tasks 1 and 2's same-day migrations), matching this
  repo's convention, rather than the `2026_07_XX_000002` placeholder in
  `data-model.md`.

## Issues → resolutions (continued)

* Task 5: tests can't rely on `RefreshDatabase`'s own migration run to exercise
  the backfill, since that run happens before any factory rows exist (nothing
  to backfill yet). Every test in `BackfillBaselineRevisionsMigrationTest`
  seeds rows first, then re-runs the migration's `up()` directly via
  `include database_path('migrations/...php')` — the standard pattern for
  testing a data migration in isolation, since Artisan's migration table
  would otherwise consider it already run.
* Task 5: PHP does not allow `{$entity::class}` inside a double-quoted string
  (parse error: "unexpected token \"}\"") — `::class` after `::` isn't valid
  inside `{...}` interpolation. Had to compute `$entityClass = $entity::class;`
  as a separate statement before interpolating it into assertion failure
  messages.

## Task 6 — `FieldAutosaveController`

* **Deviation:** `AutosavableFields::validationRule()` returns a bare rule
  array (`['nullable', 'string', 'max:N', ...]`), not `['value' => [...]]` as
  `architecture.md`'s elided `$request->validate(...)` call sketch implies.
  The controller wraps it explicitly: `$request->validate(['value' =>
  AutosavableFields::validationRule($entity, $field), ...])`. Confirmed
  against task 3's actual return type/tests (`AutosavableFieldsAndHasRevisionsTest`)
  rather than the architecture doc's pseudocode.
* **Deviation/clarification:** the byte-identical no-op skip compares the
  **post-save, freshly-reloaded stored value** against the **pre-save current
  column value** (captured before assignment) — not the raw incoming request
  value against the current value, as `RevisionRecorder`'s docblock literally
  reads. Reason: for rich fields, `SanitizesRichHtml`'s set-mutator can change
  bytes on write, so comparing the *unsanitized* incoming payload against the
  *already-sanitized* stored value would produce false "changed" positives on
  a genuine no-op (type something, undo it, editor round-trips the same
  sanitized markup it loaded). Comparing both sides post-mutator is the
  correct byte-identical test and matches `architecture.md`'s worked example
  more closely than its own prose.
* **Deviation (required to satisfy the task's own test list):** a
  `manual=true` save always calls `RevisionRecorder::record()` — it bypasses
  the byte-identical no-op skip entirely, not just `RevisionRecorder`'s
  automatic-only coalescing. Without this, two manual saves in a row with the
  identical value would write only one row (the second would be skipped as
  byte-identical), contradicting the task file's own required test: "`manual=
  true` always inserts a new `origin: manual` row, even when called twice in
  immediate succession (proving it bypasses coalescing)." `00-overview.md`'s
  "manual never coalesces" decision is honored at the `RevisionRecorder` level
  either way; this just extends the same intent to the controller's pre-check.
* **Scope note:** only `PATCH /autosave/{entity}/{id}/{field}` (`autosave.
  update`) was added to `routes/web.php`, per this task's explicit scope —
  `architecture.md`'s routes block also shows the history/compare/revert
  routes, which belong to tasks 10–11 and are deliberately not added yet.
* **Issue → resolution:** Pint's `fully_qualified_strict_types` fixer (see
  task 3's log entry for the same pattern) rewrote `SceneContentsChanged`'s
  `{@see \App\Http\Controllers\FieldAutosaveController}` / `{@see \App\Services\
  SceneReferenceMatcher::syncScene()}` docblock references into real `use`
  imports. Harmless (both classes exist by this task) — left as Pint produced
  it, confirmed idempotent via `composer lint -- --test`.
* Tests (`tests/Feature/FieldAutosaveTest.php`) needed `assertSame(2, ...)`,
  not `1`, for "one revision created" on a fresh entity's first autosave:
  `RevisionRecorder::record()` calls `ensureBaseline()` first, which seeds a
  `baseline` row for the pre-edit value before the new `automatic` row is
  written — so the first-ever autosave of a non-empty field always produces
  two rows, not one. Asserted both counts explicitly (total = 2, `origin:
  automatic` with the new value = 1) rather than assuming a single insert.

## Task 7 — `resources/js/autosave/store.js` state machine

* **Deviation/clarification:** `mapResponse(status, opts)`'s second parameter
  is `{ headers, wasReplay }`, not a bare `headers` object as the task's own
  shorthand ("`mapResponse(status, headers)`") and `architecture.md`'s prose
  suggest — the 403 → `forbidden-after-replay` distinction (this task's own
  requirement) needs the replay flag passed in explicitly, since nothing in a
  plain HTTP response tells the module whether this PATCH was a queued replay.
  Both fields default (`headers = {}`, `wasReplay = false`) so callers that
  only care about the simple cases can omit the options object entirely.
* **Addition beyond the task's literal text:** `scheduleRetry(callback,
  delayMs)`, a one-line `setTimeout` wrapper, was added alongside the pure
  `retryDelayMs()` backoff calculator. The task file asked for backoff timing
  "parameterized so tests can inject fake timers" — `retryDelayMs()` alone is
  a pure function with nothing for a fake timer to control, so a thin
  scheduling wrapper was added specifically so `store.test.js` could exercise
  `vi.useFakeTimers()`/`vi.advanceTimersByTime()` per the task's own instruction,
  and so task 8's Alpine adapter has one shared place to schedule a retry
  rather than reaching for the global `setTimeout` itself. This is the one
  function in the module with a side effect; everything else is a pure
  `(input) => decision` transform per the task's binding "no side effects"
  decision.
* No deviation needed for the retry-after header lookup: axios lowercases
  response header names, but the lookup in `retryAfterMsFromHeaders()` is
  case-insensitive anyway so a plain test double (`{ 'Retry-After': '5' }`)
  and a real axios response (`{ 'retry-after': '5' }`) both work without the
  caller needing to know which shape it has.
* `vitest`/`jsdom` were already devDependencies (added by the sibling
  `expand-tip-tap` feature's task 03) and `npm run test` was already wired in
  `package.json` — no new tooling was needed for this task, contrary to
  `handoff.md` §9.12's framing of vitest as something this feature introduces.

## Task 8 — Alpine adapter + `x-autosave-field` component

* **Design decision (not spelled out in the task file):** `field.js` reads/writes
  the field's current text via `this.$root.querySelector('textarea')`, never
  Alpine's `$refs`. `<x-wysiwyg>` mounts its own nested `x-data` scope with its
  own `x-ref="textarea"`; Alpine's `$refs` only resolves refs declared inside the
  *current* component's own scope, so `autosaveField`'s `$refs` cannot see a ref
  declared inside a nested `wysiwyg` component. A plain `querySelector` reaches
  the always-present, progressive-enhancement `<textarea>` regardless of which
  kind (`plain` bare textarea, or `<x-wysiwyg>`-wrapped) the field is — one
  code path for both, matching wysiwyg.blade.php's own "a real `<textarea>` is
  always there" pattern.
* **Extracted pure functions for testability, per the task's own instruction:**
  `storageKeyFor()` (existing-entity vs. `new:` create-form key shapes) and a new
  `shouldAutosave(dirty, id)` — the dirty-only gate applied identically to the
  debounce tick, blur, and Ctrl-S. Not named in the task file, but needed so the
  "dirty-flag gating function" the task asks to vitest-test is an actual
  standalone function rather than logic buried inside `onInput()`/`flush()`.
* **Known gap, deliberately not fixed in this task:** `restoreDraft()` writes
  the recovered text into the underlying `<textarea>` and dispatches a bubbling
  `input` event, which is correct and sufficient for the `plain` kind (a bare
  textarea) and for a JS-off/no-mount fallback. For a `<x-wysiwyg>`-wrapped
  field, though, `wysiwyg.js` has no listener that reads an externally-written
  `textarea.value` back into the live Tiptap document — `syncTextarea()` only
  flows editor → textarea, never the reverse — so a restored draft would not
  visibly reappear inside an already-mounted rich/markdown editor until the
  page is reloaded. Fixing this needs a small addition to `wysiwyg.js` (e.g. a
  `setContent`-driving custom event listener), which is `wysiwyg.js`'s file, not
  this task's scope (`field.js` + `autosave-field.blade.php`) — flagged here so
  task 9 (which does the real wiring into live edit views) picks it up rather
  than assuming Restore already works end-to-end for rich fields.
* **Issue → resolution:** an initial vitest case tried to unit-test the
  `localStorage` quota-exceeded eviction path by monkeypatching
  `window.localStorage.setItem` inside jsdom. The mock was never actually
  invoked by `field.js`'s `writeDraft()` — jsdom's `Storage` implementation
  does not allow a plain-property override of `setItem` to take effect the way
  a normal object method would (confirmed via a throwaway debug test: the call
  counter stayed at 0 after the "mocked" assignment). Rather than fight the
  environment, this is left to the manual checklist exactly as `testing.md`
  already lists it ("`localStorage` quota exhaustion behavior (eviction, not a
  crash)" under "Manual checklist") — the vitest suite only covers the
  round-trip (`readDraft`/`writeDraft`/`clearDraft`), not the eviction branch.
* **Deviation:** the Blade component test
  (`tests/Feature/AutosaveFieldComponentTest.php`) needed
  `View::share('errors', new \Illuminate\Support\ViewErrorBag)` before calling
  `Blade::render()` — `<x-input-error>` reads `$errors`, which a real HTTP
  request gets for free from `ShareErrorsFromSession` middleware, but
  `Blade::render()` bypasses the HTTP kernel entirely and never sets it. Not
  mentioned in the task file; needed for the component to render at all outside
  a real request.
* **Note for whoever asserts on the rendered `url`/`data-hash` values:** Blade's
  `@js()` helper (used for the `x-data="autosaveField({...})"` config) encodes
  with `JSON_HEX_*` flags but not `JSON_UNESCAPED_SLASHES`, so a rendered route
  URL appears with escaped slashes (`http:\/\/...`), not verbatim. The
  component test's URL assertion accounts for this explicitly
  (`str_replace('/', '\/', $expectedUrl)`) rather than asserting the raw
  `route()` string.
* The `revisions.index` history link and the `localStorage`-restore banner's
  "Compare" affordance both route through `Route::has(...)` guards, since
  `revisions.index`/`revisions.compare` are task 10's routes — this component
  must already work standalone (its own scope explicitly excludes wiring into
  any real view) before those routes exist.

## Task 9 — Wire `x-autosave-field` into existing views + global badge

* **Issue → resolution (the one real bug this task hit):** `<x-autosave-field
  ... @if ($form) form="{{ $form }}" @endif />` silently broke Blade's
  component-tag compiler — the rendered HTML showed the literal, uncompiled
  `<x-wysiwyg ...>` tag text instead of the editor, with no PHP error at all
  (`AutosaveFieldComponentTest`'s rich/markdown-kind tests and
  `WysiwygFormTest` caught it; a raw `curl`/manual look would have shown a
  broken page, but the failure mode is silent enough that it's worth flagging
  for future maintainers). Root cause: Blade's `x-component` tag compiler
  parses the opening tag's attribute list structurally (`name="value"` /
  `:name="value"` pairs); splicing a raw `@if (...) ... @endif` directive
  into the middle of that list isn't a supported attribute form and the
  compiler gives up silently rather than erroring. Fix: always emit the
  attribute (`form="{{ $form }}"` on the plain `<textarea>`, `:form="$form"`
  on `<x-wysiwyg>`), letting it render empty when `$form` is null. This is
  safe per the HTML living standard: a `form` attribute that doesn't resolve
  to a real `<form id>` is treated exactly like the attribute being absent
  (falls back to the nearest ancestor `<form>`), so `form=""` is a harmless
  no-op for every field that already sits inside its own `<form>` tag.
* **Addition beyond the task's literal text:** `autosave-field.blade.php`
  gained a new optional `form` prop (task 8 didn't need one, since its only
  test usage never had this problem). Needed because
  `resources/views/projects/edit.blade.php`'s "Book metadata" and "Book
  front & back matter" cards — which hold `rights`/`dedication`/
  `acknowledgements`/`preface`/`postface` — sit *below* the closed
  `</form id="project-edit-form">` and associate every input via HTML5's
  `form="project-edit-form"` attribute instead of DOM nesting. Without
  forwarding this, those five fields would silently stop submitting with the
  rest of the form on a full-page Save (autosave would still work, since it
  PATCHes independently of any `<form>`).
* **Deviation:** `resources/views/codex/partials/fields.blade.php` is shared
  between the create and edit codex-entry forms (`$entry` is `null` on
  create). Autosave needs a persisted id to PATCH against, so the
  `description` field is `x-autosave-field` only when `$entry !== null`;
  the create form keeps its original plain `<x-wysiwyg>` block, submitted
  with the rest of the "Create" form as before. Not spelled out in the task
  file (which only lists "every existing edit view"), but a direct
  consequence of task 6/8's "dirty-only against an existing entity" design —
  there is no entity to autosave against until the record exists.
* **Deviation:** the `dedication`/`acknowledgements`/`preface`/`postface`
  fields on `projects/edit.blade.php` were plain `<textarea>`s before this
  task (registered as `FieldKind::Markdown` in `AutosavableFields`, per task
  3). Swapping them to `x-autosave-field` upgrades them to the full
  `<x-wysiwyg markdown>` editor (toolbar, live preview) instead of the bare
  monospace textarea they used to be — this is a direct, intended
  consequence of the registry's existing `FieldKind::Markdown` mapping (the
  same component `scenes/edit.blade.php`'s `contents` field already used),
  not a new design decision made in this task.
* **Global badge scope decision:** `open-questions.md` #5's
  `forbidden-after-replay` copy calls for "surfacing the pending value inline
  since it's already in `localStorage`". Implemented as copy text only on
  the new global badge (`resources/js/autosave/badge.js`'s `BADGE_COPY`) —
  no separate value-display surface was built, because the typed text is
  never cleared from the live editor/textarea on a failed save (`field.js`'s
  `save()`, task 8, already leaves it in place on every non-200 outcome), so
  it's already sitting there, selectable/copyable, without any additional
  plumbing. Deliberately did not touch `field.js`'s core logic to add a
  second value-surfacing mechanism, per this task's explicit "does not
  include... field.js's core logic (tasks 6–8)" scope note.
* **New file `resources/js/autosave/badge.js`** (registered in
  `resources/js/app.js` alongside `registerAutosaveField`/`registerWysiwyg`):
  the global lower-right badge's `Alpine.data('autosaveBadge', ...)`, built
  as a separate module from `field.js` (small-aggregate split, one file per
  concern) with its state→copy/style/navigability lookups (`labelFor`,
  `classesFor`, `isNavigable`) exported as pure functions so vitest can cover
  the tables directly, matching this feature's existing split between pure
  logic and the thin Alpine-wiring layer (`store.js`/`field.js`).
* Badge placed at `z-40`, one below `x-modal`'s `z-50` (the only other
  fixed-position component) — confirmed a modal's backdrop naturally covers
  the badge rather than the two competing for the same layer (per
  `00-overview.md`'s "must not collide" decision). Verified visually via the
  `run-imagoldfish` skill (no modal open on the pages checked; the collision
  itself is the one item left on the manual checklist, per the task's own
  "not automated" note).
* Verified end-to-end via the `run-imagoldfish` skill against a real
  `php artisan serve` + built assets: typing into `projects/1`'s `rights`
  field showed the per-field "saved" indicator and the new global "Saved"
  badge, and `Project::find(1)->rights` was persisted server-side by the
  autosave PATCH alone (no form submit). A subsequent full-form "Save and
  stay" on the same page updated `name` and left the autosave-persisted
  `rights` value untouched, confirming the two save paths coexist on one
  page without clobbering each other.

## Task 10 — `RevisionController::index`/`compare`, `RevisionDiffer`

* **`jfcherng/php-diff` compatibility check (this task's required first sub-step):
  confirmed usable, adopted as-is.** `composer require jfcherng/php-diff` resolved
  and installed cleanly (v7.0.1, released 2026-06-22 — one month old, actively
  maintained through v7), requires only `php >=8.3` with no Laravel coupling at
  all. No hand-rolled fallback diff was needed; `handoff.md` §6's "unverified since
  before Laravel 13" warning is resolved. `RevisionDiffer` wraps `DiffHelper::
  calculate(..., 'Inline', ['context' => Differ::CONTEXT_ALL], ['detailLevel' =>
  'word', ...])` — the library's own word-level inline HTML renderer, not a custom
  LCS implementation.
* **Addition beyond the task's literal text:** `Revision::user(): BelongsTo` was
  added — task 1's model had no such relation (it wasn't needed until a view had
  to display "who wrote this revision"). Eager-loaded as `user:id,name` in the
  history index, never pulling in anything revision-value-adjacent.
* **Addition beyond the task's literal text:** `RevisionOrigin::label()` and a new
  `x-revision-origin-badge` component, following the existing `SceneStatus::
  label()` + `x-scene-status-badge` pattern in this codebase, for the history
  page's "origin badge" column. Not mentioned in the task file, but the column is
  explicitly required by `ui.md`'s "History page" spec and there was no existing
  origin-to-label mapping to reuse.
* **Design decision (current-value marker never hydrates `value`):** the newest
  revision (by `created_at`/`id`) for an (entity, field) pair is always exactly
  equal to the entity's current column value, by construction of
  `RevisionRecorder::record()`/the controller's no-op skip (task 6) — a save
  either writes the actual persisted value as a new/updated row, or is skipped
  entirely when nothing changed, so the newest row can never go stale relative to
  the live column. The "Current" badge is therefore just "is this the newest row
  ID", a cheap `pluck('id')` comparison, never a `value` comparison — satisfying
  00-overview.md's "list queries never hydrate value" invariant without needing a
  hash column on `Revision` at all.
* **Deviation/addition — "compare with previous" and default from/to:** the task
  file scopes `compare()` to `?from=&to=` but doesn't say what happens when they're
  omitted, or how the history page links into compare in the first place (`ui.md`
  only says "Compare view" exists, `handoff.md` doesn't cover a UI entry point).
  Implemented: (1) `compare()` defaults to the two most recent revisions when
  `from`/`to` are absent; (2) the history index computes a "Compare with previous"
  link per row (using the full unfiltered id order, so it stays correct even
  under an active label search) and a "Compare latest two" button when there are
  at least two revisions. An explicit `from`/`to` pair that fails to resolve to a
  real revision for this (entity, field) 404s via `findOrFail`, it does not fall
  back to the default silently.
* **Design decision:** `compare()` always diffs chronologically (`$from` = the
  earlier of the two by `created_at`, regardless of which query param the caller
  labeled "from"/"to") — a caller passing them reversed still gets a sensible
  old→new diff rather than an inverted one.
* **Addition beyond the task's literal text:** an `editUrl` ("Back to editing")
  link on the history page, resolved via a small `RevisionController::
  EDIT_ROUTES` slug-to-route-name map. Kept local to the controller rather than
  added to `AutosavableFields` — that registry is about which model+field pairs
  autosave, not the app's own edit-route naming, and deriving the route name from
  the slug mechanically (e.g. `Str::plural($entity)`) breaks for `codex` (already
  plural).
* **Issue → resolution (test-suite update needed, not a bug in this task):**
  `AutosaveFieldComponentTest::test_history_link_is_omitted_until_the_revisions_
  route_exists` (task 8) asserted the History link was absent specifically
  *because* `revisions.index` didn't exist yet. Now that this task registers it,
  the `Route::has()` guard in `autosave-field.blade.php` resolves true and the
  link renders — the test's premise is obsolete, not broken. Renamed/rewritten to
  assert the link now renders with the correct URL, rather than leaving a
  permanently-failing assertion in place.
* **Issue → resolution (environment quirk, not app code):** verifying this task's
  runtime surface via `run-imagoldfish` against the default port 8000 initially
  500'd with `Class "Jfcherng\Diff\DiffHelper" not found`, even though
  `composer require` had installed it on the host and `composer test`/`composer
  lint` both passed. Root cause: this repo's `docker-compose` dev stack
  (`a-vibe-coded-story-organizer_app_dev`) was already running and bound to host
  port 8000 (`0.0.0.0:8000->80`), silently answering every request instead of the
  host-native `php artisan serve` `scripts/serve-app.sh` started — the container
  uses its own named `vendor` volume, isolated from the host's `vendor/` where the
  new package actually landed. Confirmed via `docker ps` and the exception trace's
  `/app/...` paths. Fix: re-ran `scripts/serve-app.sh --port 8123` and verified
  against that port instead — not a code issue, but worth flagging for whoever
  next verifies a runtime surface in this repo with the Docker dev stack already
  up: check `docker ps` before trusting a port-8000 500/200 either way.
* Verified end-to-end via the `run-imagoldfish` skill (against port 8123, per the
  issue above, with a real `php artisan serve` + built assets): typed into
  `acts/1`'s Description field, watched autosave persist it, opened the History
  icon link, saw the real baseline + autosaved rows with the correct "Current"
  marker, and followed "Compare with previous" to a real rendered word-level diff
  (`<ins>`/`<del>` spans matching the actual typed text). Also confirmed the
  field switcher (`revisions/scene/1/description`) renders the Description/Notes/
  Contents tabs with the current field highlighted and a well-formed "No
  revisions yet." empty state.

## Task 11 — `RevisionController::revert`

* **Addition beyond the task's literal text:** `RevisionController::index()`/
  `compare()` both now also pass a `baseHash` (the current stored value's
  sha256) to their views — needed so every revert form on those pages can
  carry the same base-hash hidden field FieldAutosaveController's PATCH uses.
  Not itself part of task 10's scope (that task predates revert entirely);
  added here since it's the minimal, obvious place to compute it once per
  page render rather than duplicating the hash computation per row.
* **Addition beyond the task's literal text — validation, not just
  sanitization:** the task's own scope line calls for "the same
  sanitization/validation the normal save path uses (via
  AutosavableFields::validationRule()/the model's mutators)". Assignment
  (`$entity->{$field} = $revision->value`) alone re-runs the model's mutators
  (sanitization), but not the character-cap/markdown-syntax validation a
  normal autosave PATCH enforces. Added an explicit
  `Validator::make(['value' => $revision->value], ['value' =>
  AutosavableFields::validationRule($slug, $field)])->validate()` before the
  assignment, so an older revision that would violate a rule tightened since
  it was recorded (e.g. a lowered character cap) fails loudly (422) rather
  than silently reintroducing now-invalid data. Not covered by this task's
  own required test list, but a direct reading of its scope line.
* **Design decision — conflict response is a plain 409 abort, not JSON:**
  unlike FieldAutosaveController::update() (an XHR endpoint that always
  returns JSON), `revert()` is a normal Blade `<form>` POST from a
  `x-dialog`-confirmed button. On a base-hash mismatch it calls `abort(409,
  ...)`, which Laravel renders as its default HTTP-exception error page
  (no custom `resources/views/errors/409.blade.php` exists in this app, and
  none was added — a stale-hash revert is a rare race condition, not a
  path worth a bespoke error page). `tests/Feature/RevertRevisionTest.php`
  asserts the status code directly; the plain abort page is an acceptable,
  minimal response for an edge case this rare.
* **Design decision — no `run_matcher`/`SceneReferenceMatcher` wiring on
  revert:** `expanded/architecture.md`'s revert code sketch doesn't call
  `SceneReferenceMatcher::syncScene()` or dispatch `SceneContentsChanged`,
  and this task's own scope note ("does not touch RevisionRecorder... does
  not include any change to history/compare listing logic beyond adding the
  button") doesn't mention it either — left out. A future task/spec revision
  can add it if reverting `Scene.contents` is later found to need the same
  coarse-trigger reference sync a normal save gets.
* **New reusable component `resources/views/components/
  revert-revision-button.blade.php`:** one `x-dialog`-confirmed "Revert to
  this" button + its own uniquely-named dialog (`revert-revision-{id}`),
  shared verbatim between the history row (`revisions/index.blade.php`) and
  both sides of the compare view (`revisions/compare.blade.php`, one button
  each for `from`/`to`) — `ui.md`'s "Revert" section explicitly calls for
  a button in both places behind the same confirm component, so this avoids
  writing the same Blade twice. `x-dialog` itself (`resources/views/
  components/dialog.blade.php`) existed since some earlier point in this
  codebase but had no real caller anywhere else — this is its first actual
  use outside its own docblock example; the codebase's only other delete
  confirm pattern (`x-delete-button`, `edit-actions.blade.php`) uses a plain
  `onsubmit="return confirm(...)"` native browser dialog instead, but
  `handoff.md`/`ui.md` explicitly name `x-dialog` for this feature's
  destructive-feeling confirms (revert here, the retention purge panel in
  task 13), so `x-dialog` was used as directed rather than matching
  `x-delete-button`'s native-confirm pattern.
* **Route wiring note:** `revisions.revert`'s `{revision}` route parameter
  resolves via plain Eloquent route-model binding (not the `{entity}` slug
  gate `autosave.update`/`revisions.index`/`revisions.compare` use) — a
  `Revision`'s own `revisionable_type` is always a real, already-registered
  model class by construction, so there is nothing to gate against an
  unregistered slug the way there is for the other three routes.
* **Manual verification quirk (environment, not app code):** driving this
  through the `run-imagoldfish` Playwright driver, `text=` and
  `:has-text()` locators without an explicit `nth=` index silently resolved
  to the *first* matching element in DOM order across multiple
  same-page `x-dialog` instances (one per history row) — e.g. clicking the
  first `.bg-red-600:has-text("Revert")` submit button hit the *closed*
  dialog's hidden button instead of the just-opened one's, timing out as
  "not visible" even though the correct dialog was visibly open on screen.
  Not a bug in the feature; worth remembering for whoever next drives a page
  with several instances of the same reusable confirm component: index by
  the same `nth=` position as the trigger button that was clicked, not by
  text alone. Verified end-to-end this way: seeded an older
  `origin: manual` revision on a real Act's `description` via tinker,
  opened `/revisions/act/1/description`, clicked "Revert to this" on the
  older row, confirmed via the `x-dialog`, and observed a new "Reverted to
  19 July 19:54" / `origin: Reverted` row appear at the top marked
  "Current" — with the two older rows left completely unchanged below it —
  and confirmed via `artisan tinker` that `Act::find(1)->description` now
  holds the older row's value.

## Task 12 -- RevisionSetting + scheduling + RevisionPurger + purge command

* **The flagged edit to task 1's code, done as instructed:** `Revision::prunable()`
  now reads `RevisionSetting::current()->retention_days` instead of
  `config('revisions.retention_days')` directly. Existing tests in
  `RevisionDataModelTest` that call `config(['revisions.retention_days' => 90])`
  before invoking `prunable()` still pass unchanged: each test runs against a
  fresh `RefreshDatabase` connection with no `revision_settings` row yet, so
  `RevisionSetting::current()`'s lazy-create reads that same config value at
  first access -- no test needed touching.
* **Design decision -- "category" is not the same taxonomy as `RevisionOrigin`:**
  `RevisionPurger`'s four categories are `automatic` / `manual` / `labeled` /
  `imported`. Three map directly to an origin; `labeled` instead matches
  `whereNotNull('label')` regardless of origin (a manual, automatic, or reverted
  revision can all carry a label) -- this is a deliberate cross-cutting slice,
  per `handoff.md` section 4.3's own "automatic / manual / labeled / imported"
  phrasing for the storage panel breakdown, not a fifth `RevisionOrigin` case.
  `RevisionPurger::CATEGORIES` documents this explicitly since it's easy to
  assume the four categories mirror the enum one-to-one.
* **Design decision -- dry-run and real run share one query builder:**
  `RevisionPurger::purge()` computes `count()`/`sum('size_bytes')` from a cloned
  copy of the same `queryFor()` builder used for the real `delete()`, so a
  `--dry-run` immediately followed by a real run is guaranteed to report
  matching counts (this task's own required test) -- there is no separate
  "preview" query to drift from the deletion query.
* **New reusable value object `App\Support\RevisionPurgeResult`** (`count`,
  `sizeBytes`), following the existing `RevisionDiffResult` (task 10) pattern in
  the same directory rather than returning a bare array -- task 13's controller
  is the second caller and can reuse it directly.
* No factory was added for `RevisionSetting`, matching `ImportSetting` (its own
  sibling singleton) which also has none -- `RevisionSetting::current()`'s
  lazy-create is exercised directly in tests instead of via a factory.
* Verified `model:prune --pretend`'s exact output format from Laravel's own
  `PruneCommand` source (`vendor/laravel/framework/.../Database/Console/
  PruneCommand.php`) rather than guessing: `"{$count} [{$model}] records will be
  pruned."` (brackets around the FQCN) -- the task's own test list only says
  "reports the count", not the exact wording.
