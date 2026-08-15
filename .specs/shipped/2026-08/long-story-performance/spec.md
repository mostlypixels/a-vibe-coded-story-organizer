---
status: shipped
shipped: 2026-08-14
planned: 2026-08-14
expanded: 2026-08-14
---

# Long Story Performance

The Story overview (`StoryController::index`, `story.index`) renders the whole
project as one document: every scene's Markdown, rendered inline. This is fine
for the Melusine seed but falls over on a real imported novel.

Measured on a 210k-word, 143-scene import (project 4):

- DB query: **6.7 ms** — eager loading is healthy, no N+1.
- Markdown render of 143 scenes: **178 ms**, recomputed every request.
- Full Blade render: **~495 ms**.
- HTML sent to the browser: **2.0 MB**, all 143 scenes eager, each an Alpine
  component started `open: true`.

The query is not the problem. The cost is re-rendering 1.2 MB of Markdown on
every request and shipping the entire story as one giant DOM.

The page also has a purpose that shapes the fix: it is where an author moves
scenes visually *within a chapter*. So the chapter is the unit that must stay
whole on screen — pagination can never split a chapter across pages.

## Goals

- Story overview stays responsive (server render and browser paint) for
  100k+ word, 100+ scene stories.
- Keep the scene-reorder surface intact — a chapter's scenes are always shown
  together on one page.
- Cut the repeated Markdown-render cost — it should not scale with a re-read of
  the whole story on every page load.

## Non-goals

- No change to how scenes are authored, stored, or word-counted.
- Not touching the small-story experience — a project set to "Entire book"
  looks and behaves exactly as today.
- Not the EPUB / static-site / public-share render paths, except that a shared
  Markdown cache, if added, should not break them (they also call
  `Scene::renderedContents`).
- No infinite-scroll / SPA rewrite. No size-based auto default.

## Rough approach

**Per-project render mode**, following the `PublicationSetting` pattern (one row
per project, lazy default, no auto-create, behind `ProjectPolicy`):

- **Single chapter** — default. Paginate the overview one chapter per page.
  A page is a small, self-contained reordering surface; render and payload no
  longer scale with story length.
- **Entire book** — opt-in. Today's whole-story-on-one-page view, for authors
  of short works who prefer it. Never forced on.

Around that:

- **TOC stays whole-book** — it is the navigator. In Single-chapter mode,
  clicking a chapter loads that chapter's page and scrolls to it.
- **Word-count totals** (chapter / act / book) still computed over the whole
  tree regardless of mode.
- **Cache rendered Markdown** keyed on content, so `Scene::renderedContents`
  stops re-parsing unchanged scenes. Secondary once pagination lands, but it is
  what keeps "Entire book" and the EPUB path fast; must stay correct for the
  other render paths.

Open, for expansion:

- Page-address scheme and how the TOC and `#chapter-N` anchors resolve to a
  page (query param vs. path segment; anchor stability when chapters are
  added/removed).
- Whether the Markdown cache is a stored column (invalidated on save via the
  existing `word_count` `booted()` write path) or a keyed cache store.
