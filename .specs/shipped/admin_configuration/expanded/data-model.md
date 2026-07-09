# Admin Configuration — Data Model

## v1 adds no tables (recommended)

With the recommended answers, **no migrations are required**:

- **General settings** reuses the existing `CrawlerSetting` singleton unchanged (same columns,
  same `current()` accessor, same `UpdateCrawlerSettingRequest`).
- **Appearance & accessibility** is an empty placeholder — nothing to persist yet.
- **Export / import** reads and writes *existing* tables; the artifact is a file, not a table.
- **Database configuration** (read-only v1) reads runtime config, persists nothing.

This is a deliberate KISS choice: don't add a settings store before there is a second setting
to store.

## Future: a generalised settings store (flag, not v1)

When **Appearance & accessibility** gains real options, resist bolting columns onto
`CrawlerSetting` (it is specifically the *crawler* policy singleton, documented as such). Two
future paths, to decide when that work is specced — **not now**:

- A small **`app_settings` key/value table** (or a single-row `AppSetting` singleton mirroring
  the `CrawlerSetting::current()` pattern) for global preferences. Cast JSON where structured.
- Or per-section singletons following the exact `CrawlerSetting` precedent (one row,
  `current()`, config-seeded default).

> [!NOTE]
> Keep the "default lives in config + column default, kept equal" discipline the crawler
> setting documents if a new singleton is introduced.

## Export / import artifact shape (if Q3 approves building it)

Not a DB schema, but the contract the importer validates. Recommended v1 artifact = a `.zip`:

- `data.json` — a versioned document of the aggregate rooted at `User` → `Project` →
  (`Plotline`, `Event` + pivot, `Act`→`Chapter`→`Scene`) and the Codex
  (`codex_entries`, `codex_aliases`, tags + pivot, `codex_attributes`,
  `codex_attribute_values`, `codex_media` **metadata**). Include a top-level `version`/schema
  tag so the importer can reject incompatible files.
- `media/…` — the actual `codex_media` files, since media lives **on disk**, not in the DB
  (`documentation/architecture.md` → *The Codex*, `CodexMediaService`).

Invariants the importer must preserve (all documented in `architecture.md`):

- **Main plotline**: exactly one `is_main` plotline per imported project (don't duplicate; the
  `Project::booted` hook auto-creates one — importing must not create a second).
- **Position ordering**: `position` values on acts/chapters/scenes/codex_attributes must be
  imported explicitly (model `creating` hooks are suppressed under bulk/`WithoutModelEvents`).
- **Attribute-timeline baseline**: every valued (entry, attribute) keeps its Start-anchored
  baseline; prefer replaying through `AttributeTimeline` over raw inserts.
- **Fixed Start/End events**: `is_fixed` bookends and the containment window must survive.
- **Rich-text safety**: rich HTML fields are HTMLPurifier-sanitised on write via model
  set-mutators — imported values must pass through those mutators, never be raw-inserted.

> [!WARNING]
> These invariants are exactly why a naive `DB::table()->insert($json)` restore is unsafe.
> An importer must go through the models/services (or replay the same invariants) — which is
> also why Q3 must decide whether this is v1 scope or its own spec.

## Database configuration — no persisted state (v1)

Read-only display only. If Q4 ever approves switching backends, the connection choice belongs
in **`.env` / `config/database.php`**, not a DB row (you can't reliably store "which database
to use" *inside* the database you're leaving). That reinforces treating conversion as a CLI/ops
task, not app state.
