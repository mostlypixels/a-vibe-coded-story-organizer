# 07 — Sweep: layouts and navigation bar

36 usages, but the most visible surface in the app.

## Scope

`resources/views/layouts/app.blade.php`, `guest.blade.php`, `public.blade.php`, and
`navigation.blade.php` (18 usages on its own).

## Depends on

06 (the nav link components it renders).

## Key decisions already made

- The nav band uses the `nav` family, decided in the grill:
  - `bg-navy-950` (the bar) → `nav`
  - `bg-ocean-900` / `bg-ocean-800` / `hover:bg-ocean-700` (project picker, dropdown triggers) →
    `nav-raised` and its hover
  - `text-aqua-100` → `nav-content`; `text-aqua-200` / `text-aqua-300` → `nav-content-muted`
  - `focus:ring-aqua-300` → `focus`
- `layouts/app`'s `bg-ocean-700` header band → `nav-raised`. It reads as part of the nav stack,
  not as page content.
- `bg-gray-100` page shell (all three layouts) → `surface`.
- `layouts/guest` is the login page. Per the grill it gets a custom design **later** and is not
  a theming target beyond swapping its hue classes for tokens — do not redesign it here.
- The nav bar's contrast is judged against `nav`, not `surface`. `nav-content` on `nav` is a
  text pair: 4.5:1.

## Consult

`expanded/architecture.md` → *Migration map* → *The nav band*.

## Tests

- `NoHueNamedColorsTest` allow-list loses `resources/views/layouts/`.
- `NavigationTest`, `PageTitleTest`, `SceneShareTest` (renders `layouts/public`) stay green.
- Walk the nav in a browser under both presets, including the mobile/responsive menu and the
  project picker dropdown — the responsive branch has its own colors and is easy to miss.
