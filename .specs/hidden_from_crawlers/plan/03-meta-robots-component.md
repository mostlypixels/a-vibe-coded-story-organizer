# Task 03 — Meta-robots component & layout wiring

## Scope

- `resources/views/components/robots-meta.blade.php`:
  ```blade
  @props(['force' => false])
  @if ($force || \App\Models\CrawlerSetting::current()->isHidden())
      <meta name="robots" content="noindex, nofollow">
  @endif
  ```
- Wire `<x-robots-meta />` into the `<head>` of:
  - `resources/views/layouts/app.blade.php`
  - `resources/views/layouts/guest.blade.php`
  - `resources/views/welcome.blade.php`
  - `resources/views/layouts/public.blade.php` → **replace** the existing hardcoded
    `<meta name="robots" content="noindex, nofollow">` with
    `<x-robots-meta :force="true" />`, preserving the surrounding explanatory
    comment (shared scenes stay hidden regardless of the global toggle).

## Explicitly NOT in this task

- No settings UI (task 04). Tests set model state via `CrawlerSetting::current()->update([...])`.
- Does not touch robots.txt (task 02).

## Depends on

Task 01. (Independent of task 02.)

## Key decisions already made (binding)

- Content string is `noindex, nofollow`, single source in the component.
- All four layouts; `public` forced. `app` included for uniformity even though it
  is behind `auth`.

## Consult

`expanded/architecture.md` (component + wiring), `expanded/ui.md`, `expanded/overview.md`
(acceptance criteria 5), `00-overview.md`.

## Tests (extend `CrawlerSettingTest.php`)

- Hidden (default) → `get('/')` `assertSee('name="robots"', false)` and
  `assertSee('noindex', false)`.
- Hidden off → `get('/')` `assertDontSee('name="robots"', false)`.
- Shared-scene page (`public` layout) is `noindex` even with hidden **off**. If
  building a real share link is heavy, cover the `:force="true"` branch with a
  direct Blade render (`Blade::render('<x-robots-meta :force="true" />')` asserting
  the tag) — but prefer the real route if the existing scene-share tests make it easy.
