# Testing

## The separation (the tests that matter most)

The whole feature is "tracked ≠ autosaved". These pin it:

- **`name` has no autosave endpoint.** A POST to the autosave route for `codex`/`name`
  returns 404, exactly as today. Same for `aliases` and `tags`. This is criterion 6 and the
  bug the design is shaped to avoid.
- **Codex attribute values stay untracked.** Changing an attribute value writes no
  edit-time revision. The existing prohibition has a docblock but should have a test.
- **Every autosaved field is still autosavable.** One test over
  `AutosavableFields::REGISTRY` asserting the endpoint still accepts each — cheap insurance
  against a caller being switched to the union door by mistake.

## Recording

- renaming an entity writes one revision with the old and new name reachable.
- adding, removing and renaming an alias each write a revision; the stored value is the
  canonical sorted form.
- **reordering aliases without changing the set writes nothing.** This is what the sort buys.
- the same for tags.
- a save that changes nothing writes no revision, for tracked fields as for autosaved ones.
- **the relation-sync ordering trap:** a save that changes only aliases must record. A test
  that changes the name *and* the aliases would pass even if the record ran too early —
  change only the set.

## One save, one save point

- a save touching a description and a name yields **one** save point listing both fields.
- a manual save that closes an open autosave window yields one save point, badged `Saved`.
- the first-ever save still shows its baseline row, and that row is not a peer save point.
- origin ranking: a save point holding `Revert` and `Automatic` rows badges `Reverted`.

## Summaries

- a rename summary names both values.
- a set summary names the member added or removed; several changes collapse to counts.
- `change_count` for a set counts changes (added + removed), not members.
- a summary is computed at write time — the list query must not select `value`.
  `RevisionHistory`'s docblock states this; assert the query, not the rendering.

## Retention

- tracked-only rows are `Manual` and survive pruning. Assert it directly — it is the
  documented growth path in `data-model.md`.
- `Automatic` rows still prune as today.

## Empty state

Reproduce before fixing (see `ui.md`):

- a never-saved entity shows "No history yet."
- a filtered view with no matches shows the filter message.
- an unfiltered view of an entity with history shows neither.

## Authorization

- a non-owner gets 403 on the history, compare and revert routes for an entity carrying
  tracked fields — the same coverage the existing fields already have.
