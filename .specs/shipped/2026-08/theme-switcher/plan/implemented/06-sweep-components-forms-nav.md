# 06 — Sweep: form and navigation components

~120 usages. Same mechanics as 05.

## Scope

The rest of `resources/views/components/`: form controls and `input-label` / `input-error`,
`chip-picker`, `color-picker`, `event-picker`, `revision-picker`, `autosave-*`, `revision-*`,
`icon-*` (the whole icon-button family), `nav-link`, `responsive-nav-link`, `sidebar-link`,
`navigation/`, `delete-*`, `edit-actions`, `application-logo`, the layout components
(`admin-layout`, `edit-layout`, `revisions-layout`).

## Depends on

05.

## Key decisions already made

- This slice owns the bulk of the **41 `focus:ring-ocean-500` / 4 `focus:border-ocean-500`**
  usages. All → `focus`. Losing a focus affordance in a rename is the likeliest regression in
  this whole spec; spec 1 already had to restore one on `nav-link`.
- `nav-link` / `responsive-nav-link` use the `nav*` family: `text-aqua-100` → `nav-content`,
  `hover:text-white` → the pair's strong end, `border-nav-active` → `accent`.
- `--color-nav-active`'s fuchsia placeholder disappears here — `accent` takes a real value that
  clears **3:1** on the nav band. It is non-text UI, so 3:1 not 4.5:1.
- `navigation/dropdown-trigger` has **no focus ring** (pre-existing, recorded in spec 1's
  `standing-issues.md`). The file is open anyway; add `focus:ring-2` to match `nav-link`. If
  you skip it, say so in `resolution-log.md`.
- `color-picker` renders `PlotlineColors::PRESETS` — those are **plotline data, not theme
  tokens**. Leave them alone; they are user-chosen content colors.

## Consult

`expanded/architecture.md` → *Migration map* → *Interactive* and *The nav band*.

## Tests

- `NoHueNamedColorsTest` allow-list loses `resources/views/components/`.
- `AutosaveFieldComponentTest`, `FormControlComponentTest`, `IconButtonComponentTest`,
  `NavigationTest`, `RevisionHistoryTest` stay green.
- Keyboard-check one focus ring per component family in a browser. The test suite cannot see a
  missing ring.
