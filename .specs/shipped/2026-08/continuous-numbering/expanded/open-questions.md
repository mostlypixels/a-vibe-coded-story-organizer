# Open questions

1. **Does the EPUB number over the filtered tree or the full story?** `filteredTree()` drops
   chapters with zero scenes and acts left empty. Numbering over the full story would export
   "Chapter 1, 2, 4".
   → **Neither — the EPUB stops filtering.** Authors organise before they write, so an empty
   chapter is a deliberate placeholder, and renumbering around it shifts every later chapter
   the moment it gets written. Empty chapters export as heading-only pages (no filler text)
   and acts with no chapters keep their divider, so export numbers always equal app numbers.
   The `nothingToExport` guard moves to "the project has no scenes anywhere". *(Reversed
   during the planning grill — see `resolution-log.md`.)*

2. **Are scenes numbered project-wide or restarted per act?** Spec says "independently of
   chapters" and stops there.
   → **Project-wide.** Same rule as chapters; anything else needs a second concept.

3. **Is the new "In chapter" column on the scenes list sortable?** Sorting every scene in the
   project by its within-chapter position interleaves chapters meaninglessly.
   → **No.** Plain heading.

4. **Does the Story Overview show scene numbers?** Spec lists the story outline under Scenes
   but the outline shows scene *names* today.
   → **Yes**, as a muted `12.` prefix on the scene row.

5. **Does the `?sort=position` URL token get renamed to `number`?**
   → **No.** Bookmarks and the `x-sortable-header` contract stay; only the ordering behind it
   changes.

6. **Should acts become rank-derived too?** Acts don't restart, so the spec doesn't ask — but
   deleting an act leaves the same numbering gap chapters have, and the tree walk produces the
   act rank anyway.
   → **Yes.** One extra map, one line of walk, and "displayed numbers never have gaps" becomes
   a single rule instead of an exception for acts. This applies everywhere acts are numbered:
   `ActController::index` + `acts/index`, `acts/edit`, both act sites in `story/index`, and
   the EPUB's act headings and nav labels.

9. **Do untitled scenes get project-wide numbers in the EPUB nav?** `sceneNavTitle()` is the
   only place a scene number reaches a reader.
   → **No.** It stays per-chapter ("Scene 3"). A project-wide count means nothing to someone
   browsing under a chapter heading.

10. **Does the website book layer show chapter numbers?** It shows titles only today — there
    is no number there to make continuous.
    → **Add them**, formatted by the same `ChapterTitleFormat` publication setting the EPUB
    obeys, so one setting drives both exports.

7. **Is one extra query per public scene page view acceptable?** The page has no tree loaded.
   → **Yes.** Two indexed id/position columns over one project; measure before adding a cache.

8. **What happens to the number of a chapter whose act was reordered mid-session, in an open
   tab?** Nothing recomputes client-side outside the scene-swap case.
   → **Accept staleness.** Full page loads are correct; this is not a collaborative editor.
