# 07 — Sweep: layouts and navigation bar

36 usages, but the most visible surface in the app.

## Scope

`resources/views/layouts/app.blade.php`, `guest.blade.php`, `public.blade.php`, and
`navigation.blade.php` (18 usages on its own).

**Plus one vocabulary amendment, settled during task 05** (see `resolution-log.md`): add a
`scrim` token — `ALL` 45 → 46, `config/themes.php` gains it in both presets, `app.css` gains
its `var()` reference, and `resources/views/components/modal.blade.php`'s backdrop moves from
`bg-content-muted` to `bg-scrim`. Daylight's value is `gray-500`
(`oklch(55.1% 0.027 264.364)`), which is what the backdrop paints today, so this is still
rename-only for Daylight.

`scrim` is a background nobody writes text on, so it takes no foreground partner and belongs
in `ThemeTokens::DECORATIVE` alongside `border` — the matrix must skip it or it will demand a
contrast pair that does not exist. It is deliberately **not** in `PAIRS` as a key;
`ThemePresetTest` asserts every token in `ALL` appears in `PAIRS`, so that assertion needs to
exempt `DECORATIVE` tokens.

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
