# Nav consistency — architecture

Purely a Blade/presentation change. No routes, controllers, policies, models, or migrations.

## Files touched

| File | Change |
| --- | --- |
| `resources/views/components/dropdown-link.blade.php` | Add an optional `active` prop + active class set + `aria-current` (mirrors `nav-link` / `responsive-nav-link`). |
| `resources/views/layouts/navigation.blade.php` | Wire `:active="…"` onto the desktop **Story**, **Codex**, and **Timeline** dropdown links; optionally reflect active state on the dropdown triggers (Q2). |

## Root cause

`x-nav-link` (`components/nav-link.blade.php`) and `x-responsive-nav-link`
(`components/responsive-nav-link.blade.php`) both take an `active` prop and swap class sets.
`x-dropdown-link` (`components/dropdown-link.blade.php`) does **not** — it renders one fixed
class string. That is why the desktop dropdown can't highlight anything today. Fix the
component first, then wire the caller.

## Step 1 — give `x-dropdown-link` an active state

Current component is a single `<a>` with a hardcoded class string. Refactor to the same
`@props(['active'])` + `@php $classes = … ? … : …;` shape as the other two nav components, and
add `aria-current` for accessibility:

```blade
@props(['active' => false])

@php
$classes = $active
    ? 'block w-full px-4 py-2 text-start text-sm leading-5 font-semibold text-navy-900 bg-aqua-50 no-underline hover:no-underline focus:outline-none focus:bg-aqua-100 transition duration-150 ease-in-out'
    : 'block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 no-underline hover:no-underline hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active) aria-current="page" @endif>{{ $slot }}</a>
```

Notes:
- The non-active branch is **byte-for-byte the current class string** — existing dropdowns
  (Settings menu, Timeline, Codex) are unchanged until a caller passes `:active`.
- Active colors are chosen for the **white dropdown panel**, not the dark nav bar, so they
  reuse the *light-background* active palette from `responsive-nav-link` (`bg-aqua-50`,
  `text-navy-900`, `font-semibold`) rather than `nav-link`'s dark-bar palette. Final visual is
  an `ui.md` decision.
- Default `active` to `false` so the ~dozen existing `<x-dropdown-link>` calls that pass no
  prop keep working.

## Step 2 — wire the desktop Story dropdown

In `navigation.blade.php`, the desktop Story block (`:90-104`) currently has bare links. Add
`:active` using the **same expressions the responsive menu already uses** (`:209-221`) so
there is one source of truth for "what counts as active":

```blade
<x-dropdown-link :href="route('projects.story.index', $project)"
                 :active="request()->routeIs('projects.story.*')">
    {{ __('Story Overview') }}
</x-dropdown-link>

<x-dropdown-link :href="route('projects.acts.index', $project)"
                 :active="request()->routeIs('projects.acts.*') || request()->routeIs('acts.*')">
    {{ __('Acts') }}
</x-dropdown-link>

<x-dropdown-link :href="route('projects.chapters.index', $project)"
                 :active="request()->routeIs('projects.chapters.*') || request()->routeIs('chapters.*')">
    {{ __('Chapters') }}
</x-dropdown-link>

<x-dropdown-link :href="route('projects.scenes.index', $project)"
                 :active="request()->routeIs('projects.scenes.*') || request()->routeIs('scenes.*')">
    {{ __('Scenes') }}
</x-dropdown-link>
```

## Step 3 — wire Codex and Timeline for consistency (recommended)

The feature is "nav **consistency**." Apply the same to the other two desktop dropdowns using
the matchers already in the responsive menu:

- **Codex types** (`:64-68` loop) — reuse the enum-aware matcher from `:196`:
  ```blade
  :active="request()->route('type') === $codexType->routeKey()
           || request()->route('codexEntry')?->type === $codexType"
  ```
- **Attributes** (`:70`) — `request()->routeIs('projects.codex-attributes.*') || request()->routeIs('codex-attributes.*')` (matches `:201`).
- **Timeline → Plotlines / Events** (`:40-46`) — `request()->routeIs('projects.plotlines.*') || request()->routeIs('plotlines.*')` and the `events.*` equivalent (matches `:181,185`).

See `open-questions.md` Q1 for whether to keep scope to Story only or take all three.

## DRY concern — duplicated `:active` expressions

After Step 2/3 the same `routeIs(...)` strings live in **two** blocks (desktop + responsive) of
the same file. Options, in order of preference:

1. **Accept the duplication (recommended for now).** Both blocks are in one file, a few lines
   apart, and are trivial to keep in sync. This matches the existing convention — the file
   already repeats the `$project` resolution `@if` and the codex loop across desktop/responsive.
   The project guideline is "don't add abstraction before there's a second caller"; here the
   caller *is* the same file twice, but a shared helper is still over-engineering for four
   boolean expressions.
2. **Extract a small helper** — e.g. `@php $storyActive = fn ($r) => request()->routeIs("projects.$r.*") || request()->routeIs("$r.*"); @endphp` at the top of the `@if`, used by both blocks. Only if Q1 expands scope enough that the repetition becomes noisy.

Do **not** introduce a `Nav` support class or view composer for this — it would be new
architecture for a styling tweak (flag in `open-questions.md` if anyone proposes it).

## Trigger highlighting (Q2)

The dropdown **trigger** buttons (`:30`, `:54`, `:80`) are static `text-aqua-100`. To indicate
the active *section* while the menu is closed, compute a per-section `$…Active` boolean once
and swap the trigger's text/border classes (e.g. `text-white border-flame-500`, matching
`nav-link`'s active). This is the higher-value half of "consistency" but is **not explicitly in
the spec** — decision in `open-questions.md` Q2.

## Guardrails

- Keep all logic in the template as presentation-only route matching — consistent with the
  existing nav (no controller/composer changes).
- `x-dropdown-link` is also used by the **Settings** dropdown (Profile / Site settings / Log
  Out) and could later be reused elsewhere; the `active` prop must remain **optional** and
  default off so those untouched call sites are unaffected.
