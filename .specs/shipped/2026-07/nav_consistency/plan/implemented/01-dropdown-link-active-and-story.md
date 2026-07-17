# Task 01 — `x-dropdown-link` active state + Story dropdown

## Scope

Build the reusable piece and prove it on the Story dropdown:

1. **Extend `resources/views/components/dropdown-link.blade.php`** with an optional `active`
   prop (default `false`), an active class set, and `aria-current="page"` when active. The
   **non-active** class string must stay byte-for-byte identical to today so existing call
   sites (Settings dropdown, not-yet-wired Codex/Timeline) are visually unchanged.

   ```blade
   @props(['active' => false])

   @php
   $classes = $active
       ? 'block w-full px-4 py-2 text-start text-sm leading-5 font-semibold text-navy-900 bg-aqua-50 no-underline hover:no-underline focus:outline-none focus:bg-aqua-100 transition duration-150 ease-in-out'
       : 'block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 no-underline hover:no-underline hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out';
   @endphp

   <a {{ $attributes->merge(['class' => $classes]) }} @if ($active) aria-current="page" @endif>{{ $slot }}</a>
   ```

2. **Introduce the shared `@php` matcher block** at the top of *both* the desktop and responsive
   `@if ($project = …)` blocks in `navigation.blade.php`. In this task add only the **Story**
   booleans:

   ```php
   $storyOverviewActive = request()->routeIs('projects.story.*');
   $actsActive = request()->routeIs('projects.acts.*') || request()->routeIs('acts.*');
   $chaptersActive = request()->routeIs('projects.chapters.*') || request()->routeIs('chapters.*');
   $scenesActive = request()->routeIs('projects.scenes.*') || request()->routeIs('scenes.*');
   $storyActive = $storyOverviewActive || $actsActive || $chaptersActive || $scenesActive;
   ```

   (Task 02 appends the Codex/Timeline booleans to the same block.)

3. **Wire the desktop Story dropdown** (`navigation.blade.php:90-104`): add `:active` to each of
   the four `x-dropdown-link`s using the booleans above.

4. **Wire the Story trigger** (`navigation.blade.php:80`): swap its classes when `$storyActive`
   to `x-nav-link`'s active look (`text-white border-b-2 border-flame-500` in place of
   `text-aqua-100 border-transparent`). Keep the chevron.

5. **Re-point the responsive Story links** (`navigation.blade.php:209-221`) at the same
   `$…Active` booleans instead of their inline `routeIs(...)` — behavior identical, now DRY.

## Explicitly NOT in this task

- Codex and Timeline dropdowns/triggers → **Task 02**.
- CHANGELOG / documentation → **Task 03**.

## Depends on

Nothing (first task).

## Key decisions already made

See `00-overview.md` — item style (`bg-aqua-50/text-navy-900/font-semibold`, no accent bar),
trigger style (`text-white border-flame-500`), `aria-current="page"`, `active` defaults false,
matchers consolidated into one `@php` block, reuse existing color tokens.

## Docs to consult

`../expanded/architecture.md` (Steps 1–2, DRY section), `../expanded/ui.md` (styling + a11y).

## Tests (add `tests/Feature/NavigationTest.php`)

Assert on `aria-current="page"` and hrefs, **not** Tailwind classes. Owner `actingAs`, `route()`
helper, `RefreshDatabase`, factories.

- **Active item on a Story page:** GET `route('projects.scenes.index', $project)` → the **Scenes**
  link (`route('projects.scenes.index', $project)`) carries `aria-current="page"`.
- **Non-active sibling:** on that page the **Acts** link is present but **not** `aria-current`
  (guards against over-broad matchers / "everything highlights").
- **Child route highlights its section:** GET a `scenes.*` child page (e.g. `scenes.edit`) →
  Scenes item is `aria-current="page"`.
- **Trigger reflects section:** Story trigger shows its active marker on a Story page and does
  **not** on `route('projects.show', $project)` (Home). (Assert on the active class token or a
  distinguishing attribute — pick a stable hook.)
- **Default-off / no regression:** on Home, no dropdown item carries `aria-current` (proves the
  new `active` default and that untouched dropdowns are unaffected).

Run `composer test` — full suite green before moving on.
