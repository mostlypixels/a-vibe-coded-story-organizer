# Nav consistency — overview

## Problem

The primary navigation exposes each project section through a dropdown ("Timeline",
"Codex", "Story"). Only the **responsive (mobile) menu** highlights the item matching the
current page; the **desktop dropdowns highlight nothing**. A user browsing Acts, Chapters,
or Scenes on desktop gets no visual confirmation of where they are inside the Story section.

> [!NOTE]
> The source spec says *"the Codex nav already highlights the active type; the Story
> dropdown does not."* That is only true of the **mobile** menu (`navigation.blade.php:194-223`),
> where both Codex **and** Story already highlight via `x-responsive-nav-link :active`. In the
> **desktop** dropdowns, `x-dropdown-link` has no `active` prop, so *neither* Codex nor Story
> highlights. See `open-questions.md` Q1 — this changes the scope from "Story only" to "give
> the desktop dropdown an active state and wire every section."

## Goal

Make the active section/page visibly indicated in the **desktop** navigation dropdowns,
consistently across Timeline, Codex, and Story, reusing the existing active-detection
expressions the responsive menu already uses.

## Non-goals

- No routing, controller, or data-model changes.
- No redesign of the nav's structure, grouping, or colors beyond adding an active treatment.
- No change to the mobile/responsive menu's behavior (it already highlights correctly) —
  except possibly deduplicating the `:active` expressions it shares with desktop (see
  `architecture.md`).
- No new navigation sections or links.

## User stories

- As a writer on the **Scenes** index, I open the **Story** dropdown and see "Scenes"
  highlighted, so I know which list I'm viewing.
- As a writer on a **Character** edit page, the **Codex** dropdown shows "Characters"
  highlighted (parity with the mobile menu).
- As a keyboard / screen-reader user, the active item is announced (`aria-current="page"`),
  not conveyed by color alone.

## Acceptance criteria

1. On any Story page (`projects.story.*`, `acts.*`, `chapters.*`, `scenes.*`), the matching
   desktop **Story** dropdown item renders in the active style and carries `aria-current="page"`.
2. The same active treatment applies to the **Codex** and **Timeline** desktop dropdown items
   (consistency — the feature name), using the enum-aware matcher already present in the
   responsive menu for codex types.
3. Active detection reuses the exact `request()->routeIs(...)` / `request()->route('type')`
   expressions already used in `navigation.blade.php` (no new, divergent matching logic).
4. The collapsed dropdown **trigger** ("Story" / "Codex" / "Timeline") reflects when one of its
   children is active, so the section is discoverable without opening the menu — **pending
   Q2**.
5. Non-active items are visually unchanged from today.
6. A feature test asserts the active item is marked on a representative Story page and that a
   non-active item is not.
