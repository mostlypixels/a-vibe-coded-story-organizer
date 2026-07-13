# Import — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

* Source spec wording fixed: "add the datetime at the end of the url for
  differenciation" → "add the datetime at the end of the project name for
  differentiation." There is no slug/URL-by-name feature in this app (`Project`
  routes bind by numeric id) — this was a wording slip in the original spec, not a
  request for new routing infrastructure. Resolved during `plan-tasks` grilling.
* Name collision: on a case-insensitive match against the importing user's existing
  project names, the new project's `name` gets a timestamp suffix, e.g.
  `"My Novel (imported 2026-07-13 14:32)"`.
* Metadata-only media (`includes_media = false`): `CodexMedia` rows are still
  created with full metadata but no file; the UI renders a "file not included in
  this import" placeholder rather than a broken image/link.
* Anchor reconciliation (main plotline / Start-End bookends): every field the
  archive recorded is copied onto the project's auto-created rows, not a partial
  field list — a rename is real user data.
* Content security: `ContentSanitizer` composes the existing `HtmlSanitizer`/
  `RichTextFields` allow-list (never duplicated) with an import-specific
  **reject-on-violation** policy — deliberately stricter than normal form
  submission's strip-and-continue behavior, since a bulk untrusted upload warrants a
  hard failure over a silently mutated import.
* Manifest version gate: only `version = 1` (the current, only export format) is
  accepted at launch; expanding the list is a one-line change when a future format
  version exists.
* Archive size cap: not a fixed constant — a new `ImportSetting` singleton
  (mirroring `CrawlerSetting`), admin-editable on the Export & import page, default
  200 MB.
* Dual-mode processing: this app is expected to be installed by non-technical users
  with no queue worker running, so import runs **synchronously by default**.
  `ImportSetting.run_in_background` (default off) lets a technical install opt into
  queued processing via a new `ProjectImportJob`. Validation
  (`ProjectImporter::start()`) is always synchronous regardless of the toggle.
* Crash safety: because a synchronous import can be killed by a request timeout and
  a queued one by a worker restart, import progress is checkpointed onto a new
  `Import` tracking record, phase by phase (`project` → `timeline` → `story` →
  `codex`, each its own transaction). A crashed import can be **resumed** (continue
  from the last completed phase) or **discarded** (clean rollback of whatever was
  partially created) — both are explicit, user-initiated actions from the Import
  tab, never automatic.

## Deviations from the spec/plan

* Task 01: none. Built exactly the plumbing scoped — `import_settings`/`imports`
  migrations, `ImportSetting`/`Import` models, `ImportPhase` enum, `ImportPolicy`,
  `config/import.php`, `ImportFactory`. `Import.user_id` is intentionally omitted
  from `$fillable` (set only by factory/importer, never mass-assigned from request
  input) — same pattern as `Project.user_id`.

* Task 02 (additive, small): `ArchiveValidator` also requires
  `data/project/project.json` to be **present** (not just shape-valid when
  present). The task text only mandated manifest presence, but an archive
  without its root entity descriptor can never import, and rejecting it here
  gives a clear error instead of a confusing failure deep inside task 04's
  graph importer.
* Task 02: media content checks differ slightly by collection, on purpose.
  Images (`cover`/`reference_image`) require strict agreement: declared
  `mime_type` ∈ `ImportRules::IMAGE_MIME_TYPES`, finfo-sniffed mime ===
  declared, AND `getimagesizefromstring()` parses the bytes as that image type.
  Reference files use **set membership** (declared and sniffed mime both ∈
  `ImportRules::REFERENCE_FILE_MIME_TYPES`) rather than declared === sniffed,
  because libmagic's spelling for office documents varies across versions while
  the security property only needs "the bytes are genuinely an allowed document
  type". A renamed `.php` sniffs as `text/x-php` and fails either way.
* Task 02: check 6 (media file exists in the zip) is enforced only when the
  manifest says `includes_media: true` — a metadata-only export (toggle off)
  legitimately declares every media row while shipping no bytes, and must
  validate (matches testing.md's metadata-only round-trip requirement). When
  the file IS present, size + content checks run regardless of the flag.
* Task 02: the validator never extracts anything to disk — entry names are
  string-inspected and media bytes are read into memory (`getFromName` +
  `finfo->buffer()`/`getimagesizefromstring()`), so the zip-slip guarantee
  ("no file ever written outside a scoped temp path") holds trivially; the
  test asserts canary paths stay absent. The `file` paths declared inside
  `entry.json` get the same traversal check as real zip entry names before
  being joined to the entry directory (attacker-controlled JSON).
* Task 02: `config/import.php`'s `default_max_archive_kilobytes` env fallback
  now reads `ImportRules::DEFAULT_MAX_ARCHIVE_KILOBYTES` instead of a literal
  `204800` (the constant the task file assigns to that role). The migration's
  column default stays a literal by design — migrations deliberately never
  reference app classes.

* Task 03 (scoped nuance, not a code change): a raw `<script>` (or `<iframe>`,
  `<style>`, …) inside **Markdown** does *not* reject the archive, and that is
  correct: `Str::markdown()` is the GFM converter, whose DisallowedRawHtml
  extension escapes that short tag list to inert `&lt;script>` text at render
  time — so the rendered output (which is exactly what the app later echoes
  with `{!! !!}`) contains no disallowed markup. The reject path is exercised
  by raw tags *outside* GFM's escape list (`<object>`, `<img onerror>`, a
  `<div onclick>`, …), which do survive rendering verbatim. Both behaviors are
  pinned by tests so a future renderer change is caught. The same tags in a
  raw `description.html`/`notes.html` fragment always reject.
* Task 03 (known strictness, deliberate): a fenced code block with a language
  info string (```php) renders as `<code class="language-php">`, and `class`
  is outside the allow-list (only `a[href]` carries an attribute), so such
  Markdown is rejected on import. Spec-conformant ("any attribute outside the
  allow-list"), and no seeded/exported content uses language-tagged fences —
  but if task 09's round-trip ever adds one, this is why it fails.

* Task 04: the `codex_media.path` schema change happened HERE, not in task 01.
  The task file says metadata-only media rows are created "per task 01's
  schema", but task 01 never touched `codex_media` — the column was still
  `NOT NULL`. The expanded docs (open-questions.md question 2) win: a new
  migration makes `path` nullable, `CodexMedia::url()` returns null for a
  file-less row, and a new `CodexMedia::hasFile()` gives task 08's Blade a
  clean hook for the "file not included in this import" placeholder.
* Task 04 (additive, defensive): `CodexMediaService::purge()`/`purgeProject()`
  now `whereNotNull('path')` and `queueRemovals()` filters null paths, so
  deleting/discarding a project holding metadata-only imported media never
  passes null to `Storage::delete()`.
* Task 04 (additive): `CodexMediaService::storeImportedFile()` is the one new
  media-service method — it copies an extracted archive file to a freshly
  hashed path under the same disk/directory constants uploads use, so the
  importer never learns where media lives. The importer creates the row itself
  because `position` is replayed verbatim (the service's normal store path
  deliberately lets the creating() hook derive position).
* Task 04 (additive): `ImportValidationException` gained two named
  constructors — `unresolvedReference()` (a descriptor references a source id
  the archive never carried) and `unexpectedAnchorCount()` (the archive does
  not hold exactly one is_main plotline / two is_fixed events, without which
  anchor reconciliation is impossible; `ArchiveValidator` checks shapes, not
  counts, so the graph importer guards this itself before writing anything).
* Task 04 (interpretation): story-phase parentage follows the archive's
  directory nesting ("nesting mirrors ownership" per export-format.md), so the
  redundant `act_id`/`chapter_id`/`project_id` source ids in the descriptors
  are not consulted. The task text only mandates id-map resolution for the
  scene's `event_id`/`mentioned_event_ids`, which is exactly what's done.
* Task 04: which archive bookend is Start vs End is decided by the same
  canonical `(event_datetime, id)` ordering `Project::startEvent()` uses, and
  both auto-created bookend rows are resolved BEFORE either is updated —
  updating Start's datetime first could otherwise change which row
  `endEvent()` finds.
* Task 04: media byte copies happen inside the codex phase's DB transaction,
  but disk writes don't roll back — on any failure the phase deletes every
  file it copied before rethrowing, so a rolled-back phase never leaks orphan
  files (mirrors `CodexMediaService::store()`'s unlink-on-failure).

* Task 05 (path nuance): the task text says the archive lives at
  `storage/app/imports/<uuid>.zip`; the implementation stores it through the
  `local` disk (data-model.md's "private disk"), whose Laravel 12 root is
  `storage/app/private/` — so the physical path is
  `storage/app/private/imports/<uuid>.zip`, with `archive_path` holding the
  disk-relative `imports/<uuid>.zip` (the shape `ImportFactory` already used).
  Using the disk (not `storage_path()` directly like the exporter) keeps the
  files off the web-servable public root and makes the tests `Storage::fake`able.
* Task 05 (additive): `User::imports()` HasMany added — `Import.user_id` is
  deliberately not mass-assignable (task 01), so the orchestrator creates the
  row through the owner relation, same as `$user->projects()->create()`.
* Task 05 (additive, defensive): `run()` re-extracts from `archive_path` when
  the extracted directory is missing (data-model.md's "Resume: re-open
  archive_path") — a deploy/reboot that clears the extraction no longer strands
  a stalled import; and `start()` deletes the stored zip if extraction itself
  fails, since no `Import` row exists yet to track that file.
* Task 05: `failure_message` policy — an `ImportValidationException` message is
  stored verbatim (user-safe by construction); any other exception stores a
  generic phase-naming string ("The import failed while importing the codex
  data. …"), never the raw exception text (could leak paths/SQL). Each
  successful checkpoint also nulls `failure_message`, so a resumed-and-completed
  import doesn't keep a stale error. `run()` on a `completed`/`failed` row is a
  no-op rather than an error.

* Task 06: none of substance. Built exactly the HTTP layer scoped —
  `ImportProjectRequest`, `UpdateImportSettingRequest`, `ImportController`
  (store/resume/destroy), `ImportSettingController@update`, the four `admin`
  routes, and `DataTransferController::index()` now passing
  `ImportSetting::current()` + the user's non-completed imports. The queued
  `run_in_background` branch is intentionally deferred to task 07 (both `store()`
  and `resume()` call `ProjectImporter::run()` inline for now, with a code
  comment marking where task 07 adds the conditional).
* Task 06 (convention choice): MB → KB unit conversion lives in
  `UpdateImportSettingRequest::settings()` (the Form Request owns it, as the
  task allowed), keeping `ImportSettingController@update` a one-line
  `ImportSetting::current()->update($request->settings())` — mirrors how
  `GeneralSettingsController` persists `$request->validated()` straight through.
  There was no existing unit-conversion Form Request to copy, so the request-side
  placement was chosen for controller thinness.
* Task 06 (feedback contract, as decided in the task file): `store()` has two
  distinct failure surfaces — a pre-write `ImportValidationException` from
  `start()` redirects `back()->withErrors(['archive' => …])` (a form-field
  error); a mid-run failure from `run()` is caught (the row already carries a
  safe `failure_message`) and redirects to `admin.data.index` with a generic
  flash, since there is no `archive` field to attach to once past validation.
  `resume()` swallows a repeated `run()` failure the same way (row stays
  resumable) and always redirects to the Import tab.

* Task 07: none of substance. `ProjectImportJob` is a pure thin wrapper
  (`ShouldQueue`, constructor takes `Import`, `handle()` delegates to
  `ProjectImporter::run()`) with no logic of its own, and both `store()` and
  `resume()` gained the `ImportSetting::current()->run_in_background` branch that
  task 06 left marked as deferred. The branch reads the toggle **live** (not the
  value captured at start time), so `resume()` follows the current setting — pinned
  by a test that flips the toggle on between `start()` and the resume call.
* Task 07 (decided, per task file): the queued branch sets `Import->queued = true`
  before dispatch so task 08's UI can distinguish "queued, maybe still running" from
  "ran inline and crashed" — the data is recorded even though the UI is not required
  to render it differently. A queued job failure leaves the row at its last committed
  phase exactly like an inline crash; the same resume/discard actions recover both,
  so the job carries no retry/recovery logic.
* Task 07 (verification note — no rendered surface): this task adds only a job class
  and a controller branch (no Blade/JS/build asset), so verification is the green
  suite plus `Queue::fake()` assertions (nothing pushed in sync mode / inline
  completion; `ProjectImportJob` pushed + project deferred in background mode; a
  failing archive never reaches the queue; resume follows the live toggle). The
  job's DI wiring was additionally confirmed via tinker: it `instanceof ShouldQueue`,
  its `handle()` type-hints `ProjectImporter`, and the container resolves that
  service.
* Task 06 (non-completed filter): `DataTransferController::index()` lists imports
  with `phase != completed` (`->latest()`), i.e. every actionable in-progress or
  stalled import; a completed one has nothing left to resume/discard. Scoped to
  `$request->user()->imports()` so another user's stalled import never leaks.
* Task 06 (test note — Pint side effect): Pint's `fully_qualified_strict_types`
  fixer rewrote the `{@see \App\Services\...}` docblock references in the two new
  Form Requests into real `use` imports (referenced only from docblocks). Left as
  Pint produced it (clean run); not reverted.

* Task 08 (additive): `ui.md` renders `$import->archiveOriginalName()` as a method,
  but the model only had the `archive_original_name` column. Added a tiny
  `Import::archiveOriginalName()` accessor returning the column value with a generic
  `__('Uploaded archive')` fallback, so the in-progress list never renders a blank
  line (kept the display default out of the Blade, per "no presentation logic in
  templates"). This is a display helper, not the controller/service logic task 08's
  scope excludes.
* Task 08 (additive UI, in the spirit of ui.md's Feedback section): added a
  `session('status')` flash banner at the top of `admin/data/index.blade.php`. The
  queued/discarded/mid-run-failure flows and the settings-save all redirect back to
  `admin.data.index`, and the page had no surface to show their flash message. The
  banner treats the `'import-settings-updated'` marker (mirroring the crawler-settings
  form) specially and echoes any other status string verbatim (they are already
  human-readable messages from `ImportController`). Synchronous import success
  redirects to `projects.show` instead, which has no such banner — unchanged and out
  of scope.
* Task 08: the settings card lives as its own `<x-card class="max-w-md">` directly
  above the Export/Import tabbed card (ui.md's second placement option), not inside a
  tab — it configures both the size cap the upload hint reads and the background
  toggle, so it is always visible.

* Task 09 (deliberate scope choice): the round-trip test calls
  `StaticSiteExporter::export()` directly to build the archive, then POSTs the
  resulting zip through the real `admin.data.import` HTTP route. The export HTTP
  route is already covered by `ExportTest`; driving import through the full HTTP
  layer (controller → Form Request → orchestrator → gate → graph importer →
  checkpoint) is what this acceptance task needed to exercise. Nothing is mocked.
* Task 09 (deviation from testing.md's "cover + reference media"): the round-trip
  fixture uses a cover image **and a reference image**, not a reference **file**
  (PDF). Reason: `ArchiveValidator` check 6 content-sniffs every declared media
  file, and `UploadedFile::fake()->create('notes.pdf', …)` produces zero-filled
  bytes that sniff as `application/x-empty`/`text/plain`, not an allowed reference
  document mime — so a fake PDF would (correctly) fail the import gate that
  `ExportTest` never runs. Two genuine GD images exercise the same media
  metadata + byte-copy paths while keeping the archive valid.

## Issues → resolutions

* Task 03: a naive "clean and compare strings" check false-positived on any
  prose containing a double quote. Root cause: HTMLPurifier normalizes inert
  text entities on output (CommonMark renders `"` as `&quot;`; purifier
  re-emits a literal `"`), and it also normalizes `\r\n` to `\n`
  (Core.NormalizeNewlines), so cleaning "changed" content that was perfectly
  allowed. Fix: both sides of the comparison are canonicalized first —
  newlines normalized, then `html_entity_decode()` — before comparing. Entity
  decoding cannot mask an attack: encoded markup (`&lt;script&gt;`) is inert
  text on both sides, while real disallowed markup is removed from the cleaned
  side and still mismatches. Caught by the task-mandated "real exporter output
  passes" test, not by the violation tests.
* Task 08: the pre-existing `AdminConfigurationTest`
  `test_export_import_page_shows_export_form_and_import_coming_soon` asserted the old
  `#panel-import` placeholder text ("Importing a backup will be available soon.")
  that task 08 replaced. Root cause: task 03 pinned the placeholder; task 08 removed
  it. Updated that test to assert the real upload form instead (the import route +
  the "Archive (.zip)" label) and renamed it accordingly. No production regression —
  a stale assertion, not a bug.
* Task 09 (fixture calibration, caught while building): the exporter writes the
  `CodexMedia.size` **column** into `entry.json`, and the import gate checks it
  against the file's actual byte size (tolerance 1KB). `CodexMediaFactory` defaults
  `size` to a random 1KB–5MB value, which mismatches a ~300-byte 20x20 test image
  and would reject the archive on import. Fix: the round-trip fixture sets each
  media row's `size` to `Storage::disk('public')->size($path)` (the real stored
  size) so the declared-vs-actual size check passes — a fixture concern, not a
  product bug. (`ExportTest` never hit this because it never runs the archive
  through the import validator.)
