# Epub export (v1) — data model

## `projects` table — new columns

A new migration adds to `database/migrations/`:

| Column          | Type                          | Nullable | Notes                                                              |
|-----------------|--------------------------------|----------|----------------------------------------------------------------------|
| `language`      | `string`, e.g. `varchar(10)`  | no, default `'en'` | BCP-47 code (`en`, `en-US`, `fr`, …). Drives `dc:language` and every XHTML `lang` attribute. |
| `author`        | `string`                      | yes      | Free text. Drives `dc:creator` when present; omitted from OPF metadata when null. |
| `publisher`     | `string`                      | yes      | Drives `dc:publisher` when present.                                 |
| `rights`        | `string` or `text`            | yes      | Copyright/rights statement, drives `dc:rights` when present.        |
| `isbn`          | `string`, e.g. `varchar(17)`  | yes      | Stored with or without hyphens (validate, don't normalize away user formatting — see `architecture.md`). Drives a second `dc:identifier` (`opf:scheme="ISBN"`) when present. |
| `cover_image`   | `string`                      | yes      | Storage path on the `public` disk (mirrors `CodexMedia.path`'s convention — see below). Drives the OPF cover-image manifest item and the `<title>` page image when present. |

Add all six to `Project::$fillable` (`app/Models/Project.php`).

> [!NOTE]
> `language` defaults to `'en'` at the DB level so every *existing* project (migrated in
> place) gets a sane default without a data-backfill script, matching this project's "no
> data-backfill infrastructure" baseline.

## Cover image storage — reuse the Codex pattern

Per the grilled decision, cover image reuses `CodexMediaRules`/the Codex storage convention
rather than inventing a new one:

- Disk: `public` (same as `App\Services\CodexMediaService::DISK`).
- Validation rules: `CodexMediaRules::coverRules()` — `nullable`, `image`,
  `mimes:jpg,jpeg,png,gif,webp`, `max:5120` (5 MB) — reused directly, not duplicated.
- Unlike Codex media, a Project cover is **not** a separate `codex_media`-style row/table —
  it's a single nullable path column directly on `projects` (per the grilled "simpler: plain
  path column" alternative that was ultimately preferred to a new tracking table for a
  single image with no position/collection/original-name bookkeeping needs).
- Storing/replacing/deleting the file is new, small logic — a natural home is a
  `ProjectCoverService` (or a few private methods on `ProjectController`, promoted to a
  service only if a second caller appears — see CLAUDE.md "no abstraction before reuse")
  that mirrors `CodexMediaService::store()`'s store-then-catch-and-unlink-on-failure pattern.
- `Project`'s `deleting` hook (`app/Models/Project.php::booted()`) already purges Codex media
  before the FK cascade; extend it (or add a sibling hook) to also delete `cover_image` off
  the `public` disk when a project with a cover is deleted — otherwise this introduces a new
  orphan-file leak class the project doesn't currently have.

## No new tables

This feature does not need a `plotlines`-style child table, an epub-specific settings table,
or a job/queue table (export stays synchronous per the grilled decision). Everything new is
columns on `projects` plus generated-at-export-time files (temp epub zip, deleted after
streaming, mirroring `StaticSiteExporter`'s temp-zip lifecycle).

## Invariants touched

- **Position ordering** (`HasSiblingPosition` on `Act`/`Chapter`/`Scene`) is read-only here —
  the epub generator consumes `acts.chapters.scenes` ordered by `position`, exactly like
  `StaticSiteExporter::loadBookTree()`. No new invariant is introduced; skip-empty-chapter and
  skip-empty-act rules are export-time filtering, not a persisted invariant.
- **Authorization walk** (`ProjectPolicy@view` via the owning `Project`) applies unchanged —
  see `architecture.md`.
- No change to `Act`, `Chapter`, or `Scene` schemas.
