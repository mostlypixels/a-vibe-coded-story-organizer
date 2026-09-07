---
status: draft
---

# Timeline Visualization

> [!NOTE]
> **Extremely low priority.** This is a drawing surface, an era system and a change to what
> an event is, all at once — and only the last of them is small. Nothing else in the app is
> a canvas, so it starts a new pattern with one caller. Take the three goals apart before
> anyone plans this; the event end date is worth doing on its own and the picture may never
> be worth doing at all.

Three hundred years of history arrive as rows, oldest first. A planner cannot see that book 1
occupies the last eight months of it while 299 years sit behind, and cannot see the gaps. An
event is also a single moment, so a war, a reign or a curse that runs until it breaks has to
be faked with two events that the app does not know belong together. See
`.scratchpad/series-planner.md`.

## Goals

- An event can have an end as well as a start, and reads as a span everywhere it appears.
- A project defines eras: a named stretch of years, with dates counted from its start
  ("Sundering + 41").
- One picture of the timeline: events and plotlines along it, books marked where they sit,
  zoom from one month to the whole span.

## Non-goals

- No custom calendar. Months stay Gregorian; an era names a stretch, it does not rename
  the months.
- No editing from the picture. It is a way to look, not a way to write.
- No printing or export of the picture.
- No change to how a scene binds to an event.

## Rough approach

- Event end: a nullable second datetime on `events`, and `EventWindow` is where the span
  logic already lives.
- Eras: a project-owned table, and one formatter beside `DateFormat`.
- The picture is the open question, not a decision. It needs a rendering choice nothing in
  this codebase has made — the plotline colours from `PlotlineColors` are the only thing
  ready to reuse.
- Open: an event whose end is set by another event rather than a date (a curse that ends
  when it breaks). Attribute values already point at events; this would too.
