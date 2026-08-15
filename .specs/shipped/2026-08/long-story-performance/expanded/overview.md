# Long Story Performance — Overview

## Problem

`StoryController::index` → `story.index` renders the whole project on one page:
every scene's Markdown, inline. Measured on project 4 (210k words, 1 act, 21
chapters, 143 scenes):

| Phase | Cost |
|---|---|
| DB query (eager tree) | 6.7 ms — healthy, no N+1 |
| Markdown render, 143 scenes | 178 ms, recomputed every request |
| Full Blade render | ~495 ms |
| HTML to browser | 2.0 MB, 143 Alpine components, all `open: true` |

The query is fine. The cost is re-parsing 1.2 MB of Markdown per request and
handing the browser the whole novel as one DOM.

The page's job is visual scene reordering *within a chapter*, so the chapter is
the unit that must stay whole. Pagination that keeps whole chapters is therefore
free of the "don't split a reorder surface" hazard — scene reorder is already
AJAX (`resources/js/scene-reorder.js`, in-place button toggling, no reload).

## Solution

A **per-project render mode** (`Project::overview_render_mode`, enum):

- **`chapter`** (default) — paginate the overview one chapter per page.
- **`book`** — today's whole-story-on-one-page view, opt-in for short works.

## Goals

- Overview stays responsive for 100k+ word, 100+ scene stories in the default
  mode.
- A chapter's scenes always render together — reorder surface intact.
- Whole-book context preserved regardless of mode: TOC, and act/book word-count
  totals.

## Non-goals

- No change to authoring, storage, or word-count maintenance.
- `book` mode is unchanged from today — still slow on a huge story, but opt-in.
- No size-based auto default; no infinite scroll / SPA.
- Markdown-render caching is **out of scope** here (see open-questions) — `chapter`
  mode makes the overview fast without it.

## Acceptance criteria

- Fresh project defaults to `chapter` mode; overview shows one chapter with a
  prev/next pager and a whole-book TOC.
- Switching to `book` renders the full tree exactly as today.
- Setting is per-project, owner-only (non-owner gets 403), and persisted.
- Chapter numbering, act numbering, and act/book word totals are correct in
  `chapter` mode though only one chapter's scenes are loaded.
- A directly-addressed chapter URL for a chapter in another project 403s.
