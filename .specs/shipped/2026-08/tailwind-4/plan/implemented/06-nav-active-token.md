# 06 — `--color-nav-active` token and the active-link focus ring

**Depends on:** 04.

## Scope

The one intended colour change in the port, plus the accessibility fix it forces.

### The token

```css
@theme {
  --color-nav-active: var(--color-fuchsia-500);
}
```

`fuchsia` is a **deliberately loud placeholder**, not a design choice. Its job is to make the
active-navigation indicator announce itself during task 09's browser pass, so spec 2 starts
knowing exactly where that token lands.

### The four components

Replace `border-flame-500` with `border-nav-active` in:

- `resources/views/components/nav-link.blade.php`
- `resources/views/components/responsive-nav-link.blade.php`
- `resources/views/components/sidebar-link.blade.php` — both the `sidebar` and `tab` branches
- `resources/views/components/navigation/dropdown-trigger.blade.php`

### The focus ring — do not skip this

`nav-link.blade.php:5` and `responsive-nav-link.blade.php:5` carry
`focus:outline-none focus:border-flame-600` on their **active** branch only. With one token,
`focus:border-flame-600` has nowhere to go — and it is that branch's *only* visible focus
affordance, since `outline-none` removes the outline. Dropping it silently would leave a
keyboard user with no feedback when they tab onto the current page's link.

Replace it with the pattern the rest of the app already uses:

```
focus:outline-hidden focus:ring-2 focus:ring-ocean-500
```

Match the exact ring classes to what `button.blade.php` / `text-input.blade.php` use after the
codemod, so the nav is consistent rather than merely fixed.

## Key decisions

- **One token, not two.** The active/focus shade distinction is not preserved; it is replaced
  by a proper focus ring, which is better than what v3 had.
- **A `@theme` token, not a literal `fuchsia` class in Blade.** One line for spec 2 to
  repoint instead of four files, and it proves the `@theme` → custom-property → utility path
  works end to end — which is the whole point of the migration.

## Not in scope

- Choosing the real colour, or naming it semantically (`accent`) — spec 2.
- Fixing `flame`'s 2.48:1 contrast on white. Pre-existing, deferred, recorded in task 10.
- The other three components' focus states, if they have none today. Do not invent them.

## Tests

No new test — this is presentational and the components have no behaviour to assert.

**Manual, and required before calling the task done:** tab through the top nav, the mobile nav,
the sidebar and the tabs. Confirm every item shows a visible focus indicator, including the
active one. That is the regression this task is preventing.

## Consult

`../expanded/ui.md` — "The `flame` → `fuchsia` placeholder"; `../expanded/open-questions.md` §3.
