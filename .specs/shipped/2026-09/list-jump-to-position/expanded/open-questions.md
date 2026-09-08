# Open questions

Resolved in the plan grill (2026-09-08) — kept for the reasoning, not as live questions.

| Was | Resolved |
| --- | --- |
| Two dropdowns, or one control? | **One select, two buttons** — Filter and Go to. No `after` slot, no second form |
| Move buttons in a jumped-to view? | **No.** Reordering crosses chapter boundaries unfiltered; **Filter** is one button away |
| Jump inside an active search? | **No** — Go to always clears search and filter and forces story order. One rule, no hidden modes |
| Empty jump under a search? | Dead question. Nothing is ever filtered out of a jump |
| The extra `exists()` query? | Dead. `ListJump` runs one count |
| `ListJump` in Support or Services? | **`app/Support`**, beside `PageSize` — one static, no state |
| Act list gets a jump? | **No.** 3–6 rows, no page to reach |
| Codex list gets a jump? | **No.** No story order; its answer is `scoped-search` |

## Still open

1. **Does `highlight` persist too long?**
   It survives paging by design, so page 5 still shows the tail of chapter 218 marked.
   After six pages of paging it is stale colour.
   *Recommend: leave it.* Dropping it on the first page change costs a special case in the
   paginator links and takes away the "am I still in it" answer. Revisit if it reads as
   noise.

2. **Should the destination be a scene, not a chapter?**
   "Take me to the scene I edited last" is the same machinery with a finer target.
   *Recommend: not now.* That is the resume feature the prototype cut. `ListJump` would
   need no change — only the column and the ordered id list differ.
