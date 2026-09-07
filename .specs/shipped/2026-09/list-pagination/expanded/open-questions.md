# Open questions

1. **Does changing the size return to page 1?**
   Recommend yes. Page 12 at size 50 is row 551; at size 500 there is no page 12
   at all, and Laravel renders an empty page rather than clamping. Resetting is
   the only answer that is never wrong. Cost: a `return_to` hidden field.

2. **POST form or `?per_page=` query parameter?**
   Recommend the `PATCH` form to a dedicated route. The query parameter is
   fewer moving parts and reuses the toolbar's GET form, but it writes to the
   database on a GET request, and it puts the size in two places (URL and
   column) that can disagree. Say so if the extra route is not worth it.

3. **Does a filter change also reset to page 1?**
   Recommend yes, and it is free — `x-index-toolbar` submits no `page`. Confirm
   nobody wants "stay on page 4 while narrowing".

4. **Is 100 the right default?**
   Recommend 100, per the spec. At 100 rows the scenes table is a long scroll on
   a laptop. 50 would be gentler. This is the one number a writer notices on
   first run.

5. **Should `codex_attributes` really paginate?**
   Recommend yes, for the "every list, always" rule. But it is a short,
   position-ordered configuration list, not a content list, and a writer with
   more than 50 attributes is unlikely. Confirm the rule beats the exception.

6. **The scene list's chapter filter is a 400-option select.**
   Recommend leaving it — out of scope, and it is one query. But it is the next
   thing the serial writer will hit after this ships. Its own feature?

7. **Default the scene list's chapter filter to the last-edited chapter?**
   Recommend no — the serial-writer notes ask for it, but it is a separate
   behaviour (a remembered filter) with its own storage and its own surprise
   ("where did my other scenes go"). Keep it out of this feature.

8. **Does the bar go above the table as well as below?**
   Recommend below only. Above the table duplicates the toolbar row and the
   spec asks for one bar.
