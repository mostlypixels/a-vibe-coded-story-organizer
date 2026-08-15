# Open questions

1. **Cap value per column?** Recommend **5**. Enough to judge relevance without dominating a
   page that stacks eight columns; the "See all" link carries the rest.

2. **Per-page count on the domain page?** Recommend **25**. The other index pages paginate;
   revisions read `config/revisions.php`. Suggest a `config/search.php` with `cap` and
   `per_page` keys, so both numbers are one edit and testable — not two scattered constants.

3. **Truncated list *instead of* or *in addition to* the link?** (spec § Goals leaves this
   open.) Recommend **truncated list + link** — show the top `cap` rows, then "See all N".
   Showing only a link would hide the best matches behind a click.

4. **Does the main page keep its full-scan cost?** `search()` still materializes every matched
   row in memory to count them, then the view shows `cap`. Recommend **accept it** — matching
   is already a full PHP scan by design (`ProjectSearch` docblock), and the count is free once
   the collection exists. The cap targets *render* volume, which is the stated problem.

5. **Second action on `SearchController`, or a new controller?** Recommend **second action**
   (`domain`) on `SearchController` — same page family, shares `SearchRequest` and the
   `authorize('view')` walk. A new controller adds a class for no new boundary.

6. **`SearchDomain` owning `editRoute()`/`nameField()` — move the call-site literals into the
   enum, or leave them in the view?** Recommend **move to the enum** so the main page and the
   domain page read one definition. Small refactor of `search.index`; covered by the enum test.

7. **Sort order within a domain page.** Recommend **the entity's existing natural order**
   (already applied by `ProjectSearch` — position for story, `event_datetime` for events, name
   for codex). No new sort control; out of scope.

8. **Should the "See all" link appear when a column has exactly `cap` matches?** Recommend
   **no** — show the link only when `count > cap`, so a column that fits is never falsely
   truncated.
