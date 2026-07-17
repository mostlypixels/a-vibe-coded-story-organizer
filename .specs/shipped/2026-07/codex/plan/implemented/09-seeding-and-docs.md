# Codex plan — 09 · Seeding, documentation, changelog

## Goal

Demo data showcases the feature, and all project documentation is brought in sync in one consolidated pass (earlier tasks deliberately deferred this).

## Depends on

All previous tasks (01–08).

## Spec references

- [`../data-model.md`](../data-model.md) — seeding-impact section.
- [`../architecture.md`](../architecture.md) — documentation checklist.

## Files to modify

### `database/seeders/MelusineSeeder.php`

`DatabaseSeeder` runs `WithoutModelEvents`, so (same caveats as the existing plotline/position fallbacks):

- Set `position` **explicitly** on seeded `codex_attributes` and `codex_media` (the `creating` hooks won't fire).
- Create Start-anchored baselines by calling **`AttributeTimeline::ensureBaseline` / `upsertAt` directly** (service methods run fine without model events) — never assume a hook seeded them.
- Look up the project's Start/End events the way the seeder already does for fixed events (`firstOrCreate`/query by `is_fixed`).
- Seed a demo set exercising every surface: a character (e.g. Mélusine — aliases, tags, hair-color periods Start→Halloween→Back-to-class), a location with a painted-wall attribute, an organization; at least one attribute per entity type.
- Use `firstOrCreate` so re-seeding stays idempotent (existing seeder convention).

### Documentation

- **`documentation/architecture.md`** — new Codex aggregate: single-table + type enum, the `AttributeTimeline` service (first `app/Services` class), `CodexMediaService`, step-function semantics + tie-break, authorization-via-project unchanged.
- **`documentation/glossary.md`** — "Codex entry", "Attribute definition", "Attribute period / step function", "Anchor event", "Baseline (Start-anchored value)".
- **`CLAUDE.md`** — a "Codex" section paralleling the Scene↔Event notes: the type enum + `{type}` routes, the gap-free invariant and upsert semantics, no-`cover_media_id` decision, seeding caveats.
- **`CHANGELOG.md`** — `[Unreleased] → Added`: one entry for the Codex feature (entries, temporal attributes, media, as-of panels).
- Sweep `documentation/best-practices.md` for anything the new service layer pattern should mention (brief — only if it adds value).

## Key decisions already made

Docs are updated once, here, not per-task; seeder calls services directly instead of relying on hooks.

## Tests

No new tests. Full verification pass instead:

- `composer test` — entire suite green.
- `php artisan migrate:fresh --seed` — seeder runs clean twice in a row (idempotent).
- Browser walkthrough of the user stories in [`../overview.md`](../overview.md): create character with aliases/tags → set hair-color periods → scene during *Back to class* shows black → attribute applies-to filtering → uploads → non-owner 403 spot-check.
- `vendor/bin/pint` clean.

## Done when

Seeded demo project tells the hair-color story end-to-end, docs/changelog merged, suite green. Feature complete.
