# Overview — Advanced search

## Problem

There is currently no way to search across a project's content. A writer who wants to find
every place a name, place, or phrase appears has to open each Act/Chapter/Scene, Codex entry,
Event, or Plotline by hand. This feature adds one search page per project that queries across
all of those entities at once.

## Goals

* One search page, linked from the end of the main nav (`resources/views/layouts/navigation.blade.php`),
  scoped to the current `Project` (same authorization posture as every other project child
  resource — see `architecture.md`).
* A standalone, non-AJAX form: `GET` request, full page reload, results rendered server-side.
  Search state (query, mode) lives in the query string so results are shareable/bookmarkable
  and the browser back button works.
* Search runs over the **string and text fields** of:
  * **Timeline**: `Event` (title, description), `Plotline` (name, description)
  * **Story**: `Act` (name, description), `Chapter` (name, description), `Scene` (name,
    description, contents, notes)
  * **Codex**: `CodexEntry` (name, description)
* Three search modes over the (whitespace-split) query terms: **AND** (all terms must appear),
  **OR** (any term), **exact phrase** (the query matched verbatim, case-insensitive).
* Results are grouped into the same three sections as the nav dropdowns — **Timeline**,
  **Story**, **Codex** — each rendered as three columns of result tables (see `ui.md` for the
  column-assignment question).
* Each result row shows the field name (muted, small) in the first column and a snippet of the
  matching text with the query terms highlighted in the second.

## Non-goals

* No AJAX/live search — see `architecture.md` for why a standalone controller was chosen
  over `x-data` + fetch, matching the spec's explicit requirement.
* No fuzzy/typo-tolerant matching, stemming, or relevance ranking beyond "how many fields
  matched". This is a `LIKE`-based search, not a search-engine integration (see
  `architecture.md` → *Library choice*).
* No cross-project search — a signed-in user only ever searches within one `Project`, mirroring
  every other page's project scoping.
* Does not search `CodexAlias.alias` or `CodexAttributeValue.value` in v1 — flagged in
  `open-questions.md` since they are plausible follow-ups.

## User stories

* As a writer, I want to type a character's name and see every scene, codex entry, and event
  that mentions them, so I can check consistency without opening each page.
* As a writer, I want to search for an exact phrase (e.g. a line of dialogue) to find which
  scene it's in.
* As a writer, I want the field a match came from to be visible (e.g. "Notes" vs "Contents")
  so I know where to go fix something.

## Acceptance criteria

* `GET /projects/{project}/search` renders the search form; a non-owner gets a 403 (`ProjectPolicy::view`).
* Submitting a query re-renders the same page with results grouped under Timeline / Story /
  Codex, each entity's table showing entity name + muted field name + highlighted snippet.
* Switching between AND / OR / exact-phrase modes changes which rows match, per the rules
  above.
* An empty or all-whitespace query shows the empty form with no results section (not a
  validation error — see `architecture.md`).
* No N+1 queries: each entity type is fetched with one query per mode per field group (see
  `architecture.md` → *Query shape*).
