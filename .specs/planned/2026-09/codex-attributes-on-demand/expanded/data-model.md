# Data model

No new table, column, or index. `codex_attribute_values` already keys on
(`codex_entry_id`, `codex_attribute_id`, `start_event_id`).

## Meaning changes

- **Row presence is now load-bearing.** Before, every applicable attribute had rows, so
  presence said nothing. Now presence means "attached to this entry".
- `''` stays a valid value (see `documentation/features/codex.md`). It now also means
  "attached, no value yet".
- The leading-anchor invariant is unchanged: attaching goes through
  `AttributeTimeline::ensureBaseline('')`, so the pair still starts at Start.

## Existing data

Every entry made through the create form carries blank rows for all its type's
attributes. Left alone, those entries keep showing fifteen boxes.

One cleanup migration: delete rows of any (entry, attribute) pair whose values are
**all** blank.

```
DELETE FROM codex_attribute_values
WHERE (codex_entry_id, codex_attribute_id) IN (
  SELECT codex_entry_id, codex_attribute_id FROM codex_attribute_values
  GROUP BY codex_entry_id, codex_attribute_id
  HAVING MAX(LENGTH(TRIM(COALESCE(value, '')))) = 0
)
```

Keep a blank baseline when a later period of the same pair is filled — that is a real
"unknown until X" timeline, not leftovers. No `down()` restore; the data is dropped
demo data (pre-V1).

## Seeding

- `SeedsGenreBundle` and the Melusine seeders already write only the values they name,
  through `AttributeTimeline`. No change; they were always correct.
- `CodexEntryDuplicator` copies `attributeValues` rows verbatim, so attachment copies
  with the entry. No change.
- `ProjectGraphImporter` likewise carries rows through. No change.
