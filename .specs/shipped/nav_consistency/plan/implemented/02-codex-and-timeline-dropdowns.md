# Task 02 — Codex + Timeline desktop dropdowns

## Scope

Apply the pattern built in Task 01 to the remaining two desktop dropdowns, for full "nav
consistency." Reuses the now-`active`-aware `x-dropdown-link` unchanged.

1. **Extend the shared `@php` matcher block** (both desktop and responsive copies) with:

   ```php
   // Timeline
   $plotlinesActive = request()->routeIs('projects.plotlines.*') || request()->routeIs('plotlines.*');
   $eventsActive    = request()->routeIs('projects.events.*') || request()->routeIs('events.*');
   $timelineActive  = $plotlinesActive || $eventsActive;

   // Codex
   $attributesActive = request()->routeIs('projects.codex-attributes.*') || request()->routeIs('codex-attributes.*');
   $codexActive = request()->routeIs('projects.codex.*') || request()->routeIs('codex.*') || $attributesActive;
   ```

   Per-type active is computed **inside** the codex loop (enum-aware), matching the responsive
   menu's existing expression at `navigation.blade.php:196`:
   `request()->route('type') === $codexType->routeKey() || request()->route('codexEntry')?->type === $codexType`.

2. **Wire the desktop Timeline dropdown** (`navigation.blade.php:40-46`): `:active` on Plotlines
   (`$plotlinesActive`) and Events (`$eventsActive`); Timeline **trigger** (`:30`) → active look
   when `$timelineActive`.

3. **Wire the desktop Codex dropdown** (`navigation.blade.php:64-72`): `:active` on each looped
   type link (enum expression above) and on Attributes (`$attributesActive`); Codex **trigger**
   (`:54`) → active look when `$codexActive`.

4. **Re-point the responsive Timeline/Codex links** (`:181,185,194-203`) at the same booleans /
   loop expression — one source of truth, removes the remaining inline duplication.

## Explicitly NOT in this task

- Story dropdown/trigger, component change, matcher-block introduction → done in **Task 01**.
- CHANGELOG / documentation → **Task 03**.

## Depends on

**Task 01** (needs the `active`-aware `x-dropdown-link`, the trigger-active pattern, and the
`@php` matcher block to extend).

## Key decisions already made

Same as Task 01 (`00-overview.md`). Codex types iterate the `CodexEntryType` enum
(`\App\Enums\CodexEntryType::cases()`) — do **not** hardcode type strings; the enum matcher is
the existing convention.

## Docs to consult

`../expanded/architecture.md` (Step 3), `../expanded/ui.md`, `../expanded/testing.md`.

## Tests (extend `tests/Feature/NavigationTest.php`)

- **Codex type parity:** GET `route('projects.codex.index', [$project, 'characters'])` → the
  **Characters** item is `aria-current="page"`; **Locations** is not; **Attributes** is not.
- **Attributes vs types:** GET `route('projects.codex-attributes.index', $project)` →
  **Attributes** item `aria-current`; no type item is marked.
- **Timeline:** GET `route('projects.plotlines.index', $project)` → **Plotlines** item
  `aria-current`; **Events** not.
- **Triggers:** Codex trigger active on a codex page (not on a story page); Timeline trigger
  active on a plotlines page.

Run `composer test` — full suite green.
