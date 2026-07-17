# Codex plan — 00 · Overview & how to execute

This folder breaks the Codex feature (specs in [`../expanded`](../expanded/overview.md)) into ordered, commit/PR-sized tasks. Execute **01 → 09 in order**; each task leaves the suite green and the app working.

## Task list & dependencies

| # | Task | Depends on |
|---|---|---|
| [01](01-foundations.md) | Enums, migrations, models, factories | — |
| [02](02-attribute-timeline-service.md) | `AttributeTimeline` service + invariant tests | 01 |
| [03](03-entry-crud.md) | Entry routes, controller, requests, index + basic forms, nav | 01 |
| [04](04-tag-and-alias-pickers.md) | Reusable tag/alias picker components | 03 |
| [05](05-attribute-definitions-admin.md) | Attribute definitions CRUD | 01 |
| [06](06-timeline-editor.md) | Attribute value endpoints + timeline editor UI | 02, 03, 05 |
| [07](07-media.md) | Media service, uploads, right column | 03 |
| [08](08-as-of-panels.md) | "As of" panels on scenes/events | 02, 03 |
| [09](09-seeding-and-docs.md) | Seeder, documentation, changelog | all |

## Decided defaults (do not re-litigate — see [`../open-questions.md`](../open-questions.md))

- **Single `codex_entries` table** + `CodexEntryType` enum; one `CodexEntryController` with a `{type}` route segment.
- **Flat tags** taxonomy (no separate categories axis in v1).
- **Plain-text attribute values**; `applies_to` as a **JSON array** on `codex_attributes`.
- **Start-anchored step function** for temporal values — no stored end event; canonical anchor order `(event_datetime, events.id)`; anchor-identity match wins in `valueAt(Event)`.
- **Store-as-upsert** for periods: one store route, no update route, no `Rule::unique` in the Form Request (DB unique is a backstop).
- **No `cover_media_id`** — the cover is the `codex_media` row with `collection = Cover`, exposed via a `hasOne`.
- **Single-save media**: uploads and `remove_media[]` processed in the entry form's one Save.
- **Markdown descriptions** (`ValidMarkdown` + `Str::markdown()` render path).
- **`public` disk** for media (`php artisan storage:link`); not access-controlled in v1.

## Invariants every task must preserve

1. **Gap-free step function** — every (entry, attribute) with values has exactly one Start-anchored baseline; `valueAt` is total for `t ≥ Start` ([`../attribute-timeline.md`](../attribute-timeline.md)).
2. **Authorization-via-project** — no new policies; every action walks up to the owning `Project` via `ProjectPolicy`, mirrored in Form Request `authorize()`.

## Definition of done (every task)

- `composer test` green (new tests included), `vendor/bin/pint` clean.
- Conventions per [`.claude/guidelines.md`](../../../.claude/guidelines.md) and `CLAUDE.md` (thin controllers, Form Requests, `route()` in tests, eager-loading, index filtering in the controller).
- `CHANGELOG.md` and `documentation/` are **not** touched until task 09 (one consolidated docs pass).
