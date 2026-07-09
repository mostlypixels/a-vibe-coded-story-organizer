# Nav consistency — UI & accessibility

## Where the active style renders

Desktop dropdown panels are **white** (`x-dropdown` → `bg-white`, `py-1`), with items in
`text-gray-700`. The active treatment must read well on white — so it borrows the
**light-background** active palette, not the dark-nav-bar one.

| State | Today | Active (proposed) |
| --- | --- | --- |
| Text | `text-gray-700` | `text-navy-900` + `font-semibold` |
| Background | transparent (`hover:bg-gray-100`) | `bg-aqua-50` (`focus:bg-aqua-100`) |
| Hover | `hover:bg-gray-100` | keep a hover so the active item still responds to pointer |

This mirrors the active branch of `x-responsive-nav-link` (`bg-aqua-50`, `text-navy-900`) minus
the `border-l-4 border-flame-500` accent, which reads oddly inside a narrow `w-48` white
dropdown. If a stronger cue is wanted, add a left accent bar (`border-l-2 border-flame-500`
with matching `ps-` padding) — pick one in review; keep it subtle.

> [!NOTE]
> Reuse the project's existing color tokens (`navy-*`, `aqua-*`, `flame-*`) — do not introduce
> new Tailwind colors. `flame-500` is already the app's "active/selected" accent
> (`nav-link`, `responsive-nav-link`).

## Optional: trigger indication (Q2)

If we highlight the collapsed trigger, match `nav-link`'s active look adapted to the dark bar:
active trigger → `text-white border-b-2 border-flame-500`; inactive stays
`text-aqua-100 border-transparent`. This gives the closed nav the same "you are here" cue the
underlined top-level **Home** link already has.

## Accessibility

- Add `aria-current="page"` on the active `<a>` (built into the `x-dropdown-link` change) so the
  state is not conveyed by color alone — satisfies the "keyboard/semantic HTML" frontend
  guideline.
- Contrast: `text-navy-900` on `bg-aqua-50` is high-contrast; verify the chosen tokens pass
  WCAG AA (they are darker/lighter ends of the palette, so this is expected to pass).
- No change to focus order or keyboard operation of the dropdown (Alpine `x-dropdown` unchanged).

## Components

- **Reused:** `x-dropdown`, `x-dropdown-link` (extended), the `CodexEntryType` enum loop.
- **New:** none. This is deliberately a component-extension + wiring change, per the "reuse
  existing components before creating new ones" guideline.
