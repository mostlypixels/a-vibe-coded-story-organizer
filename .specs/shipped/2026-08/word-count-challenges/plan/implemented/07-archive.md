# 07 — Export and import

## Scope

- `StaticSiteExporter::addChallenges()` writing `data/challenges.json`, beside
  `addWordCountSnapshots()`.
- `DATA_VERSION` 4 → 5.
- `ProjectGraphImporter` restoring the file inside `ImportPhase::Project`, via
  `DB::table()->insert()` — no model events.
- `ArchiveValidator::LIST_ITEM_REQUIRED_KEYS` gains the file.

**Not** in this task: the ePub or reading layer; challenges are data-layer only.

## Depends on

01.

## Key decisions

- Payload is `{name, recurrence, starts_on, ends_on, target_words}` — **no ids**; a challenge
  references nothing.
- `ends_on` may be `null` and must survive the round trip as `null`.
- Read with `readJsonIfPresent`: a version-4 archive has no challenges, which is "none", not
  an error.
- Import restores rows exactly; nothing is recomputed and no snapshot is written.

## Consult

`expanded/data-model.md` → *Export / import*.

## Tests

- Round trip: challenges export and restore, `null` `ends_on` included — extend
  `WordCountGoalsArchiveTest` or add a sibling.
- `ImportRoundTripTest` — a version-4 archive with no challenges file imports cleanly.
- `ArchiveValidator` rejects a challenges entry missing a required key.
- Check whether any shipped test asserts `DATA_VERSION` or the archive member list and update it.
