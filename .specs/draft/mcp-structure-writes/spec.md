---
status: draft
---

# MCP structure writes

## Problem

The `avcso` MCP server can read everything. It can write only scenes, notes and codex entries (`.specs/shipped/2026-10/imagoldfish-mcp/`). To build or fix the structure of a story, Claude still needs the author to click through the UI: a new chapter, a renamed act, a plotline, an event on the timeline, a note category.

## Goals

- Create and update tools for:
  - books (name, description, the publication metadata fields);
  - acts and chapters (name, description);
  - plotlines (name, description, color);
  - events (title, description, date and time, fixed and approximate flags);
  - note categories (name, parent).
- Each new tool is one job, allowed by name in `.claude/settings.json`, like the first plan.
- Writes go through the same path as the matching controller. A revisioned field records a manual revision labelled "Claude".
- A new act, chapter or book goes at the end of its parent.

## Non-goals

- No deletes and no moves or reorders.
- No book front or back matter (dedication, acknowledgements, preface, postface). They are the author's prose.
- No `plotlines.is_main` writes. The main plotline invariant stays with the model and the UI.
- No cover images or other media.
- No settings, users or project creation.

## Rough approach

- Follow the first plan's tools and rules: `ReturnsToolErrors::guard()`, Form Request rule methods shared with the controller, `RevisionRecorder` with the "Claude" label, `ToolSafetyTest`.
- Extend the `list` and `show` read tools only where a new field has no read path yet.

## Open questions

- Update the project too (name, description, word goals)?
- Event writes on the Start and End events: allowed, or read-only?
- Assign an event to a plotline, or a scene to a plotline: in scope?
- Event date input: which format, and how does it map to `event_datetime` and the approximate flag?
- A move tool (scene to another chapter, chapter to another act): later, or never?
- Book `language`: free text, or the list the UI offers?
