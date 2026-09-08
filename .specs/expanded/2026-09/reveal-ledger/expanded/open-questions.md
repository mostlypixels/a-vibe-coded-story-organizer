# Open questions

1. **What happens when a reveal's scene is deleted?**
   `nullOnDelete` leaves a row that is neither "told here" nor "never told" — the shape the
   data model otherwise forbids.
   *Recommend: keep the orphan and show it.* The row means "this was told somewhere, and the
   somewhere is gone" — real information, and the writer re-marks it in one click. Cascading
   the delete instead would silently discard a decision she made, with nothing to notice.
   The cost is a third state every read path must tolerate.

2. **Is the leak warning dismissible?**
   The check is approximate by construction — a mention is not a use. One noisy entry and
   she stops reading warnings.
   *Recommend: yes, and it needs a column* (`leak_dismissed_at` on `reveals`). Without it the
   warning is unusable at series scale, which is the only scale this feature exists for. It
   is one nullable timestamp; the alternative is a feature nobody trusts.

3. **Does the attribute timeline show the reveal, or does the ledger stay separate?**
   The source spec asks this outright. An attribute value already carries story time
   (`start_event_id`); a reveal is a second axis on the same row.
   *Recommend: show both on the row, always labelled* ("True from …" / "Told in …"), and keep
   the ledger as the cross-cutting view. Splitting them means she must open two screens to
   answer one question; merging without labels is the confusion `architecture.md` flags.

4. **Should the scene picker be built here, or wait?**
   A flat select of 1600 scenes is unusable, so this feature needs a narrowed picker. That is
   a reusable component with its own design questions.
   *Recommend: build it here, as a component, not inline.* The `list-jump-to-position` feature
   just established the pattern for aiming at a place in a long list — follow it rather than
   inventing a second idiom.

5. **One reveal per fact — or one per book?**
   Criterion 7 says one. But a fact can be re-disclosed: hinted in book 1, confirmed in
   book 4.
   *Recommend: hold at one.* The source spec's non-goals rule out degrees of reveal, and
   "first told" is the question the ledger answers. If re-disclosure matters later, it is a
   second row and a `kind` column, not a redesign.

6. **Is `note` earning its column?**
   The spec asks for a nullable note and never says what it is for.
   *Recommend: keep it.* It is the escape hatch for everything this feature deliberately
   cannot express — "hinted only", "she suspects" — and one nullable text column is cheaper
   than the feature requests it absorbs.

7. **Does an event need a reveal at all?**
   An event is already a timeline entry with a datetime. "Told in" versus "happened at" is
   the same double axis as an attribute value, one level up.
   *Recommend: yes, include events* — the spec names them — but expect this to be the least
   used of the three, and do not let it shape the design.

8. **The warning covers codex entries only.** `scene_codex_entry` has no equivalent for
   attribute values or events.
   *Recommend: say so in the UI.* A warning that silently checks one fact type of three is
   worse than no warning, because it reads as coverage.
