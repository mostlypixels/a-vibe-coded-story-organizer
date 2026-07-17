# Import — open questions

Each question states a recommended answer so `plan-tasks`' grilling pass has a
concrete default to push against, not a blank slate.

## 1. Name-collision disambiguation — RESOLVED

The source spec's original wording ("add the datetime at the end of the url") was a
slip — it meant the project **name**, not a URL. `spec.md` has been corrected. There
is no `slug` column or name-derived URL in this app anyway (`Project` routes bind by
numeric `id`); this was never a routing question.

**Decision:** on a name collision (case-insensitive match against the importing
user's existing projects), suffix the imported project's `name` with a timestamp,
e.g. `"My Novel (imported 2026-07-13 14:32)"`.

## 2. How does the UI render a `CodexMedia` row with metadata but no backing file?

This situation is new: today every `CodexMedia` row that exists has bytes on disk.
Importing a metadata-only archive (`includes_media = false`) produces rows that
reference a file that was never copied.

**Recommendation:** give such a row a `path` value that resolves to nothing (e.g.
`null` before any storage write, with `path` made nullable-safe in `CodexMedia::url()`)
and have the entry/media Blade partials render a "file not included in this import"
placeholder instead of a broken `<img>`/download link, rather than fabricating a path
that 404s. Confirm with whoever owns Codex UI whether this placeholder state is
acceptable or whether metadata-only import should instead be disallowed entirely
(simpler, but loses information the archive did carry faithfully).

## 3. Reconciling the main plotline / Start-End bookends — copy which fields?

`data-model.md`'s reconciliation step updates the new project's auto-created main
plotline/bookend events in place with the archive's values. Should **every** field be
copied (`name`/`color`/`description` for the plotline; `title`/`event_datetime`/
`description` for the events), or only fields the export doc calls out as
lossless/meaningful (e.g. is a renamed "Main plotline" → "Central Arc" something the
user actually did on the source project and wants preserved, or should the label
stay generic)?

**Recommendation:** copy everything the archive recorded — the whole point of a
lossless export is that a rename is real user data, not noise to discard. This also
keeps the reconciliation logic simple (one `update()` call per anchor, no per-field
allow-list to maintain).

## 4. Markdown/HTML security depth — RESOLVED

**Decision:** compose, don't fork. The existing `HtmlSanitizer`/`RichTextFields::ALLOWED_TAGS`
allow-list stays the single source of truth for *what's allowed* — never duplicated
or re-implemented for import. Wrapped around it, import applies its own **policy**
that's deliberately stricter than normal form submission: where a normal save
(`SanitizesRichHtml`) silently strips disallowed content and saves the cleaned
result, import **rejects the whole archive** on any violation in
`description.html`/`notes.html`/rendered `contents.md` — never silently persisting a
stripped, mutated version of what the archive claimed to contain. If
`HtmlSanitizer`/HTMLPurifier itself ever needs hardening (e.g. against
attribute-level attacks within allowed tags), that's a cross-cutting fix benefiting
every feature, not something import re-implements on its own.

## 5. Which `manifest.json` `version` values does this importer support?

`documentation/export-format.md` reserves `version` for exactly this gate; the
current (and only) exported version is `1`.

**Recommendation:** `ImportRules::SUPPORTED_MANIFEST_VERSIONS = [1]` at launch. Any
other value (including future versions not yet designed) is rejected with a clear
"unsupported archive version" error — no silent best-effort handling. Expanding this
list is a one-line change whenever the export format gains a new version and this
importer is updated to understand it.

## 6. Archive size cap — RESOLVED

**Decision:** not a fixed constant — a new `ImportSetting` singleton (same pattern as
`CrawlerSetting`), admin-editable from a new "Import settings" card on the
**Export & import** page (`admin.data.index`), default 200 MB
(`config('import.default_max_archive_kilobytes')`, overridable via
`IMPORT_MAX_ARCHIVE_KILOBYTES`). See `architecture.md` → *`ImportSetting` — the
configurable size cap* and `ui.md` → *Import settings card* for the full shape.

Still worth flagging as an ops follow-up (not code in this spec): the hosting
environment's `upload_max_filesize`/`post_max_size` php.ini values must be raised to
actually allow uploads at whatever cap is configured — `ImportSetting` controls the
app's own validation ceiling, not PHP's, and a php.ini limit below the configured
setting will silently truncate the upload before Laravel ever sees it.
