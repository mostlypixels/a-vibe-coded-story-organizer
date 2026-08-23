# Birth and death — resolved decisions

All grilled and settled before planning. Kept for the record; nothing here is still open.

1. **Label wording per type.** Character Born/Died. Location Created/Destroyed. Organization
   Founded/Dissolved. One fixed pair per type; no per-entry choice.

2. **Age scope — which types?** A per-type capability, not a blanket. `CodexEntryType::tracksLifespan()`
   is true for all three current types and gates the edit-page fields, the age, and the existence
   filter. A future type that has no age (Object, Concept…) returns false and opts out.

3. **Store as FK columns or reserved attributes?** FK columns (`inception_event_id`,
   `termination_event_id`). One event link, not a step function.

4. **Moment after termination — what shows?** Nothing. The entity no longer exists, so it is hidden
   from the scene/event codex panel — not tagged. No "gone" label.

5. **Moment before inception?** Also hidden. The existence window is symmetric: the panel shows an
   entity only when `inception <= moment <= termination` (inclusive). No "not yet born" label.

6. **Age precision.** Whole years only. Keep the `Age` object the single home for a later
   "20 years, 3 months".

7. **Exclude bookends (Start/End) from the pickers?** Yes — exclude in the picker and reject
   server-side.

8. **Termination before inception (time travel)?** Allowed, not rejected. `hasInvertedLifespan()`
   then suppresses age and skips the existence filter (the entity always shows), and the edit page
   warns under the termination field to track age via an attribute. Either link may be set without
   the other.

9. **Event picker markup.** The scene page and the two codex fields share the inline "+ New event"
   flow. Extract the controller logic to a `CreatesInlineEvents` trait; extract the Blade to a
   shared partial/component if the copy is more than trivial.
