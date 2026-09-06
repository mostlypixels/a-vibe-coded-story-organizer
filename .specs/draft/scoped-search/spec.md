---
status: draft
---

# Scoped search

Search returns everything in the project, always. It is the feature a serial writer would
live in, and the one that breaks first at her size: 400 chapters means a common word hits
about 1400 scenes, the eight columns each cap at five rows, and "See all 1400 results" is
a cliff, not an answer.

She knows things the search does not ask her. Which book. Roughly where in it. Whether she
wants a scene or a character. Every one of those cuts the result set before it is built.

## Goals

- Narrow a search by book. A project with one book hides the control.
- Narrow the manuscript domains by a chapter range, in story order.
- Narrow to one kind of thing: scenes only, codex only, or one domain.
- Filters survive into the "see all" domain page and into the URL, so a search is
  bookmarkable and the back button works.
- The counts on the main page reflect the filters, not the whole project.

## Non-goals

- No change to how matching works. `ProjectSearch` stays PHP-side accent folding.
- No saved searches, no search history, no relevance ranking.
- No full-text index or search engine. That is its own decision, and this feature has to
  work before it.
- No filtering on the list-page search bars. Those already filter by act and chapter.

## Approach

- Extend `SearchRequest` with the new parameters and `SearchController` to pass them
  through. The controller stays thin.
- `ProjectSearch::queryFor()` already builds one query per `SearchDomain` and already
  joins `acts` for chapters and scenes. The book and chapter-range filters belong there,
  as `where` clauses on that join — the filtering must happen before the PHP matching
  loop, not after it, or it saves nothing.
- The domain filter skips whole domains in `search()`. A domain that is filtered out
  should never run its query.
- `SearchDomain::carriesBook()` already knows which domains a book filter can apply to.
  Plotlines, events and the codex are project-wide and are hidden, not emptied, when a
  book filter is set.

## Open ends

- How a chapter range is expressed: two chapter pickers, or one "from here on" anchored to
  the chapter she last worked in.
- Whether "scenes only" is a separate control from the eight-domain filter, or the same
  control with a grouped list.
- Whether a book filter should also hide the codex columns, or leave them project-wide
  beside the filtered manuscript ones.
