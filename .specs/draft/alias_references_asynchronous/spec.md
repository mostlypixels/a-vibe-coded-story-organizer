---
status: draft
---

# Alias references — asynchronous rescan

Follow-up to `alias_references_v1` (`.specs/planned/alias_references_v1/`).

In v1, saving a codex entry whose aliases or name changed triggers a **synchronous**
project-wide rescan of every scene's contents against the updated alias/name set (see
`alias_references_v1`'s `architecture.md` → *"Where each trigger calls it"*). For a project
with many scenes, this makes a single entry save noticeably slower.

This spec covers moving that project-wide rescan to a **background job**, for projects large
enough that the synchronous cost becomes a real UX problem.

## Open questions to work through when this is expanded

- Precedent to reuse: `ImportSetting` (`app/Models/ImportSetting.php`) is a singleton exactly
  like `CrawlerSetting`, carrying a `run_in_background` toggle consulted by `ImportController`.
  Does this feature need its own per-project or global toggle, or should it always queue once a
  project passes some scene-count threshold?
- What does the UI show while a rescan is pending (a codex entry save currently redirects
  straight back to the index with fresh data assumed correct)?
- Does the scene edit page's "Codex references" sidebar need a "still recalculating" state, or
  is showing stale data until the job finishes acceptable?
- Should this reuse a general job-queue dispatch pattern already in the app (`ProjectImportJob`
  is the only existing queued job — check `app/Jobs` for its shape) rather than inventing a new
  one?
- **Regex size safety.** v1's `SceneReferenceMatcher` builds one combined regex per project
  (alternation of every eligible entry name + alias). For a project with a very large cast, this
  regex could grow large enough to hit PHP/PCRE practical limits (`pcre.backtrack_limit`,
  overall pattern size), risking a `preg_match_all` failure on save. v1 deliberately left this
  unguarded (see `alias_references_v1`'s `open-questions.md`, third resolved block) since normal
  project sizes never approach it. When this spec is expanded, decide whether queuing large
  rescans in the background also warrants batching the regex itself (e.g. chunking entries into
  several smaller alternations) rather than assuming background execution alone is enough.
