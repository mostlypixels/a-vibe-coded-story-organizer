---
status: draft
---

# Alias references — asynchronous rescan

Follow-up to `alias_references_v1` (`.specs/shipped/2026-07/alias_references_v1/`).

In v1, saving a codex entry whose aliases or name changed triggers a **synchronous**
project-wide rescan of every scene's contents against the updated alias/name set (see
`alias_references_v1`'s `architecture.md` → *"Where each trigger calls it"*). For a project
with many scenes, this makes a single entry save noticeably slower.

This spec covers moving that project-wide rescan to a **background job**, for projects large
enough that the synchronous cost becomes a real UX problem.

## Measured baseline (2026-08-16)

Largest sample available: the seeded long test project — 143 scenes, 1.2M characters,
51 entries, 3 aliases. Measured by `codex:sync-references 4` against a **copy** of the dev
database, and by an in-memory probe (`scripts/probe-test.sh`) reproducing the same shape.

| Action | ms | queries |
|---|---|---|
| PUT, description only (no term change) | 17 | 12 |
| PUT, name changed (rescan) | 23 | 156 |
| GET the codex edit page | 57 | 14 |
| Real-data rescan, file SQLite | 30–40 | — |

`syncProject` scaling, 51 entries: 143 scenes 25 ms / 423 q · 500 scenes 66 ms / 1473 q ·
1000 scenes 137 ms / 2944 q — **linear, ~0.13 ms and ~1–3 queries per scene**.

- Saving is not slow today. The rescan costs less than rendering the edit page.
- Cost is round-trips, not the regex: `sync()` runs one SELECT per scene even when nothing
  changed (146 queries at 143 scenes with a correct pivot). Writes add only on real changes.
- Extrapolated: ~250 ms at 1000 scenes, ~1 s at 4000 — roughly 7–30× the current sample.
- Peak memory 94 MB in the probe: `syncProject` loads every scene's contents at once.
- The sample predates multiple books. The codex belongs to the project, so one rescan still
  covers every book: `Project::sceneQuery()` walks `chapter.act.book`. A project of several
  books therefore reaches the scene counts extrapolated below sooner than a single-book one,
  and one book's entry save pays for every other book's scenes. Re-measure per project, never
  per book.

Cheaper fixes to weigh against queuing, both of which also help a queued job:

- Batch the pivot diff into one query set instead of per-scene `sync()`.
- Chunk `sceneQuery()` to bound memory.

> [!NOTE]
> The numbers above come from generated content, not real prose. Import long public-domain
> books and re-measure before this spec is expanded — that decides whether the threshold is
> ever reached in practice.

## Open questions to work through when this is expanded

- Precedent to reuse: `ImportSetting` (`app/Models/ImportSetting.php`) is a singleton exactly
  like `CrawlerSetting`, carrying a `run_in_background` toggle consulted by `ImportController`.
  Does this feature need its own per-project or global toggle, or should it always queue once a
  project passes some scene-count threshold?
- Should the rescan stay project-wide, or run per book? A book the writer is not in still
  pays for the save. Splitting it changes what the job takes as its subject, so decide before
  the job shape is fixed.
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
