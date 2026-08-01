# Tailwind 4 — standing issues

**What is still true of the shipped code.** Read this before extending the feature, and
especially before starting `theme-switcher` (spec 2), which closes several of these.

Distinct from `resolution-log.md`: that file is the record of the work, actionable only while
the task was open. Everything here is **still true of the code on `master`**.

---

## Accepted costs

### The border-colour shim

`resources/css/app.css`'s `@layer base` rule restores v3's `gray-200` default border colour;
v4's own default is `currentColor`. Restoring it by editing the 88 width-only `border` usages
was rejected as Blade churn for no behaviour change. Contradicts v4's own default on purpose.
Removed by `theme-switcher` once `--color-border` exists — the rule's own comment names that
spec as its removal.

### The active nav link's focus ring

v3's only focus affordance on the active nav link was `focus:border-flame-600` (`outline-none`
removes the browser outline). Collapsing the border-color axis to one `--color-nav-active`
token would have deleted that affordance entirely, so `focus:ring-2` was added instead — a
small, deliberate improvement over v3, not a like-for-like port.

### The nav indicator's contrast

`--color-nav-active` currently resolves to `fuchsia-500`, a placeholder standing in for
`flame-500`. Like `flame`, it is under the 3:1 non-text contrast minimum on a white nav bar.
Known, deferred to `theme-switcher`, which picks the real token value.

### The desktop nav's active dropdown trigger has no focus ring

`focus:ring-2` (above) landed on `nav-link` / `responsive-nav-link`. The desktop trigger
component is `navigation/dropdown-trigger`, which has no focus ring — same as v3. Not a
regression this port introduced, but the accessibility gap the ring closed for plain nav links
is still open for dropdown triggers.

## Things the browser pass measured and left alone

Found by diffing computed styles for every element on ~40 pages against `master`, twice
(`resolution-log.md` → *Accepted differences found by the browser pass*). None are visible;
each has a reason it wasn't worth Blade churn to undo.

- **`space-y-*`/`divide-*` moved which sibling carries the spacing** (v3: `margin-top` on every
  child but the first; v4: `margin-bottom` on every child but the last). Rendering is
  identical, but a child's own `mt-*` now wins over the parent's `space-y-*` at zero
  specificity. One place hits it: `codex/partials/fields.blade.php`'s `<div class="mt-6">`
  under `space-y-10` on `codex/edit` renders a 24px gap instead of v3's 40px. Left alone
  because the same partial renders on `codex/create`, where 24px is already correct.
- **Checked checkboxes/radios keep a `gray-300` border** instead of `@tailwindcss/forms`'
  intended transparent one — v4's utilities layer now beats the plugin's base layer regardless
  of specificity. Not fixed: removing the utility would change the *unchecked* border instead.
- **`outline-hidden` (v4) computes as `outline-style: none`** where `outline-none` (v3)
  computed as `2px solid transparent`. Intended v4 shape — the transparent outline moved inside
  `@media (forced-colors: active)`, so high-contrast users keep the affordance and everyone
  else sees no change. All focus **rings** are byte-identical to `master`.
- **`rounded-full` is `calc(infinity * 1px)`** instead of v3's `9999px`. Identical pills; no
  `--radius-full` theme variable exists in v4 to reference instead.
- **Stock palette colours are OKLCH**, and a few resolve slightly differently once rendered to
  sRGB: `red-500/600`, `blue-600/800`, `green-800`, `gray-400`. Expected — this is v4's stock
  palette, not a bug in this port.
