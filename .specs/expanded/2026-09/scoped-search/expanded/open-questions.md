# Open questions

1. **Two chapter pickers, or one "from here on" anchored to the chapter she last worked
   in?**
   Recommend two pickers. The anchor needs a "last worked in" the app does not track for
   chapters, and half a range is not a range. Revisit if `list-jump-to-position` adds that
   state.

2. **Does a book filter hide the codex columns, or leave them project-wide?**
   Recommend hide, with the explanatory line. An empty codex column beside a filtered
   scene column reads as "she is not in this book", which is false.

3. **Is "scenes only" its own control?**
   Recommend no. It is the eight checkboxes with seven cleared, and a group toggle makes
   that one click.

4. **`whereIn` on chapter ids — what happens at 1000 chapters?**
   The bound-variable ceiling. Recommend shipping `whereIn` with a test at ~1000 chapters,
   and falling back to `(act.position, chapter.position)` pair predicates only if it
   actually breaks. Do not pre-build the harder query.

5. **Should an empty `domains` mean all, or should the form always submit all eight?**
   Recommend empty-means-all. Eight always-present query parameters make every URL
   unreadable and every bookmark brittle.

6. **Does the chapter-range control need `list-jump-to-position` first?**
   They need the same thing: chapters listed in story order, not by name. Recommend fixing
   the ordering in whichever ships first, and naming it as shared work rather than letting
   each write its own.

7. **Should the filters apply to the list-page search bars too?**
   Out of scope per the source spec. Recommend keeping it that way — those already filter
   by act and chapter, and two filter vocabularies on one screen is worse than none.
