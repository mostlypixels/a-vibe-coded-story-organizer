# Open questions

1. **Setting storage: column on `projects` vs. a `StorySetting` model?**
   Recommend the **column** — a single enum view-preference is a project
   attribute, not an aggregate; `PublicationSetting` earns its own table only
   because it holds ~20 export fields. Revisit if story-view settings grow.

2. **Chapter address: id vs. project-wide number?**
   Recommend **id** in `?chapter=` — stable when scenes/chapters are reordered,
   and it is what the authorization walk needs. Numbers shift and would break
   bookmarks. Cost: the URL isn't human-readable; acceptable.

3. **Markdown-render caching — in scope or deferred?**
   Recommend **deferred**. `chapter` mode renders ~one chapter (~9 ms), so the
   overview no longer needs it. Its remaining value is `book` mode on a large
   story and the EPUB export — a separate performance concern. Track as a
   follow-up spec. If we keep it, prefer a stored `rendered_contents` column
   invalidated in the same `Scene::booted()` saving path that maintains
   `word_count` (single write path, survives cache flush, shared by all render
   paths) over a keyed cache store.

4. **Where does the mode switch live — overview header vs. project edit page?**
   Recommend the **overview header**: discoverable exactly where the author
   feels the difference. Project-edit is the fallback if we want all project
   settings in one place.

5. **Default for the huge existing import (project 4)?**
   It defaults to `chapter` like everything else — no special-casing. Confirm
   the author is happy paginating rather than being auto-put in `book`.

6. **Act boundaries in `chapter` mode.**
   One chapter per page means the act header (`Act 1 — …`) shows above its first
   chapter only, or on every page as context? Recommend **always show the
   current chapter's act** as a lightweight breadcrumb/heading, so the reader
   keeps their place. Confirm.
