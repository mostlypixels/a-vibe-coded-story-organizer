# 09 — Browser pass

**Depends on:** 01–08.

> [!IMPORTANT]
> **This task is the feature's acceptance test.** Everything before it is machine-checkable and
> will all pass while the app looks wrong. Budget for it accordingly: ~30 pages, two builds,
> plus keyboard checks. It is the majority of the wall-clock time in this plan.

## Scope

Drive the app with `/run-imagoldfish`. For each page: screenshot on `master`, screenshot on the
branch, compare.

1. `git stash` / check out `master`, `npm run build`, screenshot the inventory below.
2. Return to the branch, `npm run build`, screenshot the same pages at the same viewport.
3. Compare pairwise. Every difference is either **fixed** or **written down** for task 10.

Do not rely on memory between pages. Same viewport both times, or the comparison is worthless.

## Page inventory

**Guest / auth** (the `guest` layout — the app layout's chrome does not cover these):
`/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`,
`/confirm-password`, `/` (welcome).

**Core app**: `/dashboard`, `/projects/create`, `/projects/{project}`,
`/projects/{project}/edit`, `/profile`.

**Index tables** (the `x-table` family — striping differs, so do not sample just one):
`/projects/{p}/acts`, `/chapters`, `/scenes`, `/plotlines`, `/events`, `/codex/{type}`,
`/codex-attributes`.

**Composite**: `/projects/{p}/story` (nested act→chapter→scene tree), `/projects/{p}/search`,
`/projects/{p}/revisions` — **pagination lives here; this is the `@source` check in the wild**.

**Revision diffs** — the densest `theme()` consumer in `app.css` (callouts, diff rules,
`revision-diff--visual` heading sizes): `/revisions/{entity}/{id}`, `…/compare`,
`…/{field}`, `…/{field}/compare`.

**Rich text**: any scene edit page — the Tiptap editor, `prose` styling, the slash menu
(`wysiwyg-slash*`), tables and callouts inside content.

**Admin**: `/admin`, `/admin/appearance`, `/admin/settings`, `/admin/data`,
`/admin/data/import`, `/admin/revisions`, `/admin/database`.

**Public**: `/shared/scenes/{token}` — the `public` layout with forced `x-robots-meta`, and the
only view a logged-out stranger sees.

## What to look at on every page

- Card and input hairlines — the 88 `border` usages the shim covers
- Shadow weight on cards, dropdowns, dialogs (66 `shadow-sm` → `shadow-xs`)
- Corner radii on badges, chips, buttons
- Table stripes and header bands
- Anything inside `.revision-diff`, `.callout`, `.wysiwyg-*` — the task 04 rewrite sites

## Form controls — explicit line item

Do not assume `@tailwindcss/forms` absorbs v4's changed defaults; it works by resetting exactly
the properties that changed. Check every control type in **three states — at rest, focused, and
in an error state**:

- text / email / password / number inputs
- `select`, `textarea`
- checkbox, radio
- file input (`hover:file:bg-ocean-100` exists in the codebase)

Good pages for this: `/login`, `/projects/create`, `/admin/settings`, a scene edit page, and
any form submitted empty to raise validation errors.

## Keyboard

Tab through each page type — not just look at it. Focus rings are 28 `outline-none` →
`outline-hidden` rewrites, and `outline-hidden` (unlike v4's `outline-none`) preserves the
transparent outline that forced-colors and high-contrast modes depend on. Getting those right
*is* the accessibility work in this port.

Include the nav focus ring added in task 06.

## Expected — record as intended, not as drift

- The `flame` → `fuchsia` nav indicator (deliberate placeholder)
- The active nav link's new focus ring (task 06)
- Slight saturation shift on stock palette colours on P3-capable displays (v4's OKLCH palette)

## Do not fix here

`flame`/`fuchsia`'s 2.48:1 contrast on white, under the 3:1 non-text minimum. Real, pre-existing,
and fixing it breaks the "nothing looks different" criterion. It goes to `standing-issues.md`
in task 10.

## Output

A written list of every difference found and its disposition. Task 10 consumes it. A browser
pass that produces no notes has not been done.

## Consult

`../expanded/ui.md` — the full drift table with measured counts.
