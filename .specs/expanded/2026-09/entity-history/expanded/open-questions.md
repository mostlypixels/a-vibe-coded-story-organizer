# Open questions

1. **Can a rename be reverted, or only read?**
   Reverting a name that later chapters already use is a different kind of undo from
   restoring a paragraph — the codex would disagree with published prose, silently.
   *Recommend: read-only for `name` and the sets, in this feature.* Build the record and the
   summary; do not offer revert. The revert control is per save point today, so a save point
   holding only tracked-only fields simply shows none. Revisit once writers have used the
   record and said whether they want the undo.

2. **Do tags belong in history at all?**
   A tag is a filing decision more than a story fact, and tag churn could bury a rename in
   noise.
   *Recommend: include tags, but ship the field selector with them de-emphasised* (last in
   the option order). Excluding them is a one-line registry change later; the noise is
   cheaper to measure than to predict.

3. **Tracked-only rows never prune.** They are `origin: Manual`, and `Revision::prunable()`
   selects `Automatic` only. Fifty renames are fifty permanent rows on a hot table.
   *Recommend: accept it for now, and test it explicitly* so the growth path is documented
   rather than discovered. A per-origin retention rule is a change to the retention feature,
   not this one.

4. **Should `name` be tracked on every entity, or codex entries only?**
   `overview.md` decides *every entity*, on the grounds that "when did I rename this
   chapter?" is not codex-specific and the registry entry is the same line repeated.
   *Confirm this.* Codex-only would halve the registry and the test matrix, at the cost of
   answering the question for one entity type out of seven.

5. **"When did I change her hair?" — the question that does not typecheck.**
   The writer asked for attribute-value history by name, and this feature explicitly refuses
   it: attribute values carry *story-time* history by design. What she wants is a second
   time axis (edit time) over a value that already has one (story time).
   *Recommend: out of scope, and worth its own spec.* Note in the docs that the codex
   attribute timeline answers "when in the story did it change", not "when did I change it",
   so the refusal is visible rather than silent.

6. **Does the empty state actually need fixing?**
   The condition in `revisions/index.blade.php` reads correct today. The spec may be
   describing a defaulted `$field` rather than a wrong condition.
   *Recommend: reproduce first.* If it is already right, the deliverable is a test, not a
   fix — and the spec's fifth goal is met on arrival.

7. **`TrackedFields` or a third element on `AutosavableFields`?**
   `architecture.md` decides a separate class, because widening the existing registry would
   hand `name` an autosave endpoint it must not have.
   *Confirm.* The cost is that a reader must know two registries exist; the alternative
   risks criterion 6 every time someone edits the file.
