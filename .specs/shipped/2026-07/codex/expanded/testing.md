# Codex — Testing

Follow `tests/Feature/ProjectTest.php` style: plain PHPUnit, `use RefreshDatabase`, model factories, `actingAs($user)`, the `route()` helper (never raw URLs), in-memory SQLite. Every new endpoint gets happy-path + authorization (owner 2xx / non-owner 403) + validation-failure coverage, plus the domain invariants below. Run with `composer test`.

## Factories to add

`CodexEntryFactory`, `CodexAttributeFactory`, `CodexAttributeValueFactory`, `TagFactory`, `CodexMediaFactory` (+ alias helper). Provide states like `->character()`, `->location()`, `->organization()`. Use `Storage::fake('public')` for any media test.

## `tests/Feature/CodexEntryTest.php`

- Owner can view each type's index; index filters by `type` (a character does not appear on the locations index).
- Search matches both **name** and **alias**.
- Create/store persists name, description, aliases (`aliases[]`), tags (`tags[]` — existing tags reused, new tags `firstOrCreate`d and scoped to the project).
- Edit/update happy path; non-owner gets **403** on index/create/store/edit/update/destroy.
- Validation: missing `name` → `assertSessionHasErrors('name')`; unknown `type` route segment → 404; `tags`/anchor events from **another project** are rejected.
- `attribute_baselines[]` on store: seeds a Start-anchored value per submitted attribute; an attribute id from **another project**, or one whose `applies_to` doesn't include the entry's type, is rejected.
- Destroy removes the entry **and** its media files (`Storage::disk('public')->assertMissing(...)`) and cascades aliases/tags-pivot/attribute values.

## `tests/Feature/CodexAttributeTest.php`

- Create attribute with `applies_to = [character]`; it appears on character create/edit and **not** on locations/organizations.
- `applies_to` validation: empty array rejected; non-enum value rejected (`assertSessionHasErrors`).
- Non-owner 403 on all actions.
- Deleting an attribute cascades its `codex_attribute_values`.

## `tests/Feature/AttributeTimelineTest.php` — the invariant suite

This is the highest-value test file; it guards the gap-free step function.

- **Baseline at Start**: creating the first value for (entry, attribute) anchors it at the project's *Start* event; `AttributeTimeline::ensureBaseline` is idempotent (no duplicate Start rows).
- **Resolution**: with values at Start=blonde, Halloween=green, Back-to-class=black, `valueAt` returns:
  - a datetime before Halloween → blonde,
  - exactly at Halloween → green,
  - between Back-to-class and End → black.
- **No holes / totality**: `valueAt` for any datetime ≥ Start returns exactly one value; never null when a baseline exists.
- **Upsert at an existing anchor**: posting a second value at the same anchor event for the same (entry, attribute) **updates the existing row** (assert still one row for that anchor, with the new value) — the store endpoint is an upsert; the DB unique constraint is only a backstop and no validation error occurs.
- **Same-datetime tie-break**: two anchor events sharing the exact same `event_datetime` resolve deterministically by `(event_datetime, events.id)`; `valueAt($event)` where `$event` is itself an anchor returns *that* anchor's value even when another anchor shares its datetime.
- **Cross-project anchor** rejected: `start_event_id` from another project fails validation.
- **Event deletion keeps it gap-free**: delete a *non-fixed* middle anchor event → its period disappears and `valueAt` in that gap now resolves to the **previous** value (previous period extended). Confirms `cascadeOnDelete` behavior, not a hole.
- **Start baseline protected**: `removeAt(Start)` refuses while other values exist (would break invariant #1); allowed only when it's the sole value.
- **Fixed events**: Start/End remain undeletable (existing invariant) so the leading anchor can't be orphaned.
- **"As of" via scene**: a scene whose `event` is *Back to class* resolves the attribute to black through `CodexEntry::attributeValueAt`; a scene with `event_id = null` resolves to "undetermined" (null), not a crash.
- Authorization: non-owner 403 on `codex.attribute-values.store` / `destroy`.

## `tests/Feature/CodexMediaTest.php`

- `Storage::fake('public')`; uploading a `cover` stores a file as the single `Cover`-collection row (exposed via `CodexEntry::cover()`); uploading a **new** cover replaces the old row and **deletes the old file** (still exactly one `Cover` row).
- `reference_images[]` / `reference_files[]` create multiple rows with incrementing `position` — scoped **per collection**: each collection's positions start at 1 independently, they don't interleave.
- Upload validation: oversized file and disallowed mime → `assertSessionHasErrors`.
- Per-item removal deletes the row and the file.
- Non-owner cannot upload/remove (403).

## Coverage gaps to note

The guidelines flag that Scenes/Acts/Chapters/Story have **no** feature tests yet. The Codex work adds a read path onto scenes/events ("as of" panels); add at least a focused test for `CodexEntry::attributeValueAt($attribute, $scene->event)` rather than a full `SceneTest`, unless you touch scene write paths.
