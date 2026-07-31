# Tailwind 4 — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

Resolved in the planning grill, 2026-07-31.

- **Border default: shim, not 88 edits.** v4's width-only `border` is `currentColor`; v3 was
  `gray-200`. A base-layer rule restores it. The counter-argument — invisible magic in a
  codebase written for junior developers, and "we'll remove it next spec" being how debt
  becomes permanent — was heard and accepted, conditional on a self-explaining comment at the
  rule and a `standing-issues.md` entry.
- **Codemod first, then targeted audits.** Task 01 takes a large opaque diff in exchange for a
  fast green build; tasks 03–04 exist because the codemod is unreliable in known places. The
  alternative (hand-migrating every slice) was rejected as redoing work the tool does well.
- **`var()` guard before the `theme()` audit.** Ordering chosen so the audit becomes "make the
  test pass" rather than "read 86 lines carefully". Cost accepted: the test is written against
  a still-churning build.
- **One nav token, plus a focus ring.** `--color-nav-active` only. This collapsed
  `focus:border-flame-600`, which turned out to be the active nav link's *only* focus
  affordance (`focus:outline-none` removes the outline) — so `focus:ring-2` was added rather
  than shipping a keyboard-accessibility gap. Deliberate improvement over v3 behaviour.
- **`tailwind.config.js` deleted, no `@config`.** Keeping it would leave theme values in JS,
  never becoming runtime custom properties, leaving `theme-switcher` with nothing to build on.
- **Browser floor accepted** (Safari 16.4+ / Chrome 111+ / Firefox 128+): documented, not
  enforced at runtime. Self-hosted app, small known user base.
- **Docker watcher check blocks the merge.** The failure is silent and its symptom points away
  from this PR.
- **`@tailwindcss/forms` needs no version change.** Checked npm: 0.5.11 is the current line
  (published 2026-05-12) and already installed; its peer range names v4 explicitly. A proposed
  "diff the plugin's base rules between builds" audit task was dropped as unwarranted for a
  maintained dependency — form controls are covered as an explicit browser-pass line item
  instead.
- **`admin/appearance` docblock corrected, page untouched.** Its "no later task enriches it"
  claim is falsified by `display-configurator`.

## Deviations from the spec/plan

Corrections to the expanded docs, found by probing Tailwind 4.3.3 directly during planning.

- **`ring` 3px → 1px affects one element, not 71.** The expanded spec attributed it to the 71
  `focus:ring-ocean-500` usages; those set *colour*. Widths come from `ring-2` (16) and
  `ring-1` (4), and explicit ring widths are unchanged in v4. Exactly one bare `ring` exists.
  The real volume is `border` (88) and `shadow-sm` (66).
- **The `@source` risk is `vendor/`, not `wysiwyg.js`.** The draft spec flagged Tiptap's
  JS-set `prose` classes, but `resources/js` is not gitignored and v4 auto-detects it. The
  actual gap is `vendor/…/Pagination/resources/views`, rendered by `revisions/index.blade.php`
  via `->links()`.
- **86 `theme()` calls, not 81.** 81 was the line count; five lines carry two calls.

## Issues → resolutions

_None yet._
