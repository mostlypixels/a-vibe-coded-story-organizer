# UI — Tailwind 4

No new views, no new components. This file is the drift inventory and the browser-pass
checklist, because those are the deliverable.

## Default changes, measured against this codebase

Counts are exact-token matches across the 168 files in `resources/views` and `resources/js`,
variant prefixes stripped (`focus:shadow-sm` counts as `shadow-sm`).

| v4 change | Usages here | Visible as |
|---|---:|---|
| `border`/`border-*` width-only defaults to `currentColor` (was `gray-200`) | **88** | hairlines take the element's text colour — dark rules on light cards, invisible rules on same-coloured text |
| `shadow-sm` → `shadow-xs` (whole scale shifted down a name) | **66** | every card and dropdown loses shadow weight |
| `outline-none` → `outline-hidden` | **28** | `outline-none` still *exists* in v4 but now means `outline-style: none`, removing the forced-colors accessibility fallback |
| `rounded` → `rounded-sm` | **26** | corners square up if left unrewritten |
| `rounded-sm` → `rounded-xs` | **9** | same |
| `shadow` → `shadow-sm` | **2** | same |
| `divide-*` width-only → `currentColor` | **2** | as `border` |
| `ring` bare, 3px → 1px | **1** | one element |
| Stock palette redefined in OKLCH/P3 | 35 `theme('colors.…')` calls + all Blade `gray-*`/`red-*`/… | slightly more saturated on P3 displays |

> [!NOTE]
> The source spec named `ring` 3px → 1px as hitting "71 `focus:ring-ocean-500` usages". It
> does not. Those set *colour*; width comes from `ring-2` (16 uses) and `ring-1` (4), and
> **explicit ring widths are unchanged in v4**. Exactly one bare `ring` exists. The real
> volume is `border` (88) and `shadow-sm` (66).

The upgrade codemod rewrites the renamed utilities. The `border` → `currentColor` change is
**not** a rename and no codemod can fix it — it is a default the code was relying on
implicitly, 88 times.

## The `border` decision — **settled: shim** (2026-07-31)

Two ways to absorb 88 usages; the first was chosen.

1. **Compatibility shim** — one base rule restoring the v3 default:
   ```css
   @layer base {
     *, ::after, ::before, ::backdrop, ::file-selector-button {
       border-color: var(--color-gray-200);
     }
   }
   ```
   Zero Blade churn, guaranteed zero drift. This is what the official upgrade guide
   recommends for exactly this case.
2. **Explicit `border-gray-200`** on all 88 — honest, no hidden global, and it makes spec 2's
   rename mechanical (each one becomes `border-border`).

**Chosen: option 1** — take the shim now, remove it in spec 2 when `--color-border` exists and
the rename touches those elements anyway. Doing 88 edits in a PR whose acceptance criterion is
"nothing changed" adds risk for no benefit.

Two conditions attach to it (see `open-questions.md` §1): the rule carries a comment
explaining what it restores and that spec 2 removes it, and it gets a `standing-issues.md`
entry so it stays a visible, dated decision.

## Components most exposed

Every one of these is a shared component, so a mistake multiplies:

| Component | Why |
|---|---|
| `components/table.blade.php` + `table-heading` / `table-row` / `table-empty` | borders and stripes across 6 index pages |
| `components/card.blade.php` | `shadow-sm` + border, on nearly every page |
| `components/button.blade.php`, `primary-button`, `secondary-button` | `shadow-sm`, `rounded`, `focus:ring-2`, `outline-none` |
| `components/text-input.blade.php` | `@tailwindcss/forms` + `border` + focus ring |
| `components/dialog.blade.php` | `shadow-xl`, backdrop, `outline-none` |
| `components/badge.blade.php` | `rounded-full`, tinted backgrounds |
| `components/nav-link`, `responsive-nav-link`, `sidebar-link`, `navigation/dropdown-trigger` | the `flame` → `fuchsia` swap lands here |
| `components/sortable-header.blade.php` | table header chrome |

`resources/js/wysiwyg.js:544` sets `prose prose-sm max-w-none focus:outline-none px-3 py-2`
from JS — the `outline-none` there needs the same rewrite as the Blade ones, and it is easy
to miss because it is a JS string, not a class attribute.

## The `flame` → `fuchsia` placeholder

7 border usages, all the active-navigation indicator. Wire it through one variable rather
than writing `fuchsia` into the four components:

```css
@theme {
  --color-nav-active: var(--color-fuchsia-500);
}
```

Blade uses `border-nav-active`; spec 2 repoints the one line. Note the `focus:border-flame-600`
pair in `nav-link` and `responsive-nav-link` needs a second token (`--color-nav-active-focus`)
or it will silently keep pointing at a `flame` ramp that no longer exists.

> [!NOTE]
> This is the one place the PR *intends* a visible change. Record it in the browser pass as
> expected, not as drift.

## Browser pass

Drive with `/run-imagoldfish`. Compare each page against `master` — build `master` first,
screenshot, then the branch. Screenshots side by side; do not rely on memory between pages.

**Guest / auth** — these use the `guest` layout, which the app layout's chrome does not cover:
`/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`,
`/confirm-password`, and `/` (welcome).

**Core app**: `/dashboard`, `/projects/create`, `/projects/{project}`,
`/projects/{project}/edit`, `/profile`.

**Index tables** (the `x-table` family — one is representative, but the striping differs):
`/projects/{p}/acts`, `/chapters`, `/scenes`, `/plotlines`, `/events`,
`/codex/{type}`, `/codex-attributes`.

**Composite views**: `/projects/{p}/story` (the nested act→chapter→scene tree),
`/projects/{p}/search`, `/projects/{p}/revisions` (**pagination lives here — the `@source`
check**).

**Revision diffs** — the densest `theme()` consumer in `app.css` (callouts, diff rules, the
`revision-diff--visual` heading sizes): `/revisions/{entity}/{id}`,
`/revisions/{entity}/{id}/compare`, `/revisions/{entity}/{id}/{field}`,
`/revisions/{entity}/{id}/{field}/compare`.

**Rich text**: any scene edit page — the Tiptap editor, its `prose` styling, the slash menu
(`wysiwyg-slash*` classes), tables and callouts inside content.

**Admin**: `/admin`, `/admin/appearance`, `/admin/settings`, `/admin/data`,
`/admin/data/import`, `/admin/revisions`, `/admin/database`.

**Public / shared**: `/shared/scenes/{token}` — uses the `public` layout with the forced
`x-robots-meta`, and is the only view a logged-out stranger sees.

### What to look at on each

- Card and input hairlines — the 88 `border` usages
- Shadow weight on cards, dropdowns, dialogs
- Focus rings: tab through the form on the page, do not just look at it
- Corner radii on badges, chips, buttons
- Table stripes and header bands
- Anything inside `.revision-diff`, `.callout`, `.wysiwyg-*` — the `theme()` rewrite sites

## Accessibility

The port must not regress keyboard access. `outline-hidden` (unlike v4's `outline-none`)
keeps the transparent outline that forced-colors/high-contrast modes rely on — getting the
28 rewrites right *is* the accessibility work here.

Do not fix `flame`'s 2.48:1 contrast in this PR. It is a real defect, it is pre-existing, and
fixing it here breaks the "nothing looks different" criterion. Record it in
`standing-issues.md` for spec 2.
