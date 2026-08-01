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
- **Task 10's `standing-issues.md` needs an entry for the border shim itself**, not only the
  two intended visual changes: the `@layer base` rule in `resources/css/app.css` restoring
  v3's `gray-200` border default is a deliberate, temporary accepted difference from v4's own
  behaviour, removed when the theme-switcher spec lands `--color-border`. Flagged here (task
  05) so task 10 doesn't have to rediscover it.

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
- **The codemod already wrote the border shim (task 05's deliverable) into `app.css`.** It
  emits its own `@layer base` rule setting `border-color: var(--color-gray-200, currentcolor)`
  with a generic comment. Task 01 accepts the codemod diff wholesale, so it is in the tree
  now: task 05 must *rework that rule's comment* (self-explaining, names spec 2 as its
  removal) rather than add a second one.
- **The codemod rewrote 85 of the 86 `theme()` calls.** One survives at `app.css:598`
  (`theme('borderRadius.full')`). Task 04 is a much smaller audit than planned, but it still
  owns that line and the correctness of the other 85.
- **The `var()` guard (02) only flags references with no fallback.** A literal reading of
  "fail on `references - declarations`" flags several Tailwind-core preflight variables that
  are *never* declared anywhere by design (`--default-font-feature-settings`,
  `--default-mono-font-feature-settings`, `--tw-empty`, etc.) — they exist purely as
  `var(--x, fallback)` extension points, and the browser uses the fallback rather than
  dropping anything. The guard therefore only treats a bare `var(--x)` (no second argument) as
  dangling; `var(--x, fallback)` is excluded from the reference set. `@property --x { ... }`
  is also accepted as a declaration (Tailwind emits its `--tw-*` internals that way, not via
  `--x:`).

- **Codemod kept a redundant `@source` for `storage/framework/views/*.php`.** Task 03's own
  table says to drop it (compiled output of `resources/views`, already auto-detected); the
  codemod left it in anyway. Removed — `npm run build` output shrank slightly, no class lost
  (pagination and `.prose` utilities confirmed still present).
- **Task 04's mapping table is wrong about `borderRadius.full`: there is no `--radius-full`.**
  v4's radius scale stops at `--radius-xs … --radius-4xl`; `rounded-full` is hard-coded to
  `calc(infinity * 1px)` (emitted as `3.40282e38px`) with no theme variable behind it. Writing
  `var(--radius-full)` would have dangled. `.revision-diff .diff-note` therefore carries the
  literal `calc(infinity * 1px)`, with a comment saying why, so it stays identical to the
  `rounded-full` badge component it mirrors. Spec 2 cannot make this one themeable without
  inventing its own variable.
- **Spacing is `--spacing(N)`, not the literal `calc(var(--spacing) * N)` the table names.**
  The codemod used v4's CSS function form; it compiles to exactly `calc(var(--spacing) * N)`
  (verified in the built output), so it is themeable and was left as written. Read the table
  row as naming the semantics, not the source spelling.

- **`--radius` dangling `var()` confirmed, left for task 04.** Task 02's guard already flags it
  (`css-build.test.js`, 1 failing assertion); task 02's own scope note says fixing it is task
  04's job, so it stays red through task 03 despite that task's verification bullet implying
  the guard should pass at this checkpoint — read that bullet as "passes once 04 lands," not
  literally at 03.

- **`NavigationTest.php` hard-coded `border-flame-500`/`focus:border-flame-600` strings.**
  Task 06 didn't list this file, but five assertions across
  `test_the_story_trigger_reflects_the_active_section`,
  `assertTriggerIsActive`/`assertTriggerIsNotActive`, and the responsive-menu regex in the
  revisions-highlighting test matched the literal v3 class name. Updated all five to
  `border-nav-active` alongside the component changes — the green suite would not have caught
  the class rename otherwise (it fully round-tripped: the old string is just no longer in the
  rendered HTML, string match silently stops passing to failing, not to erroring).

## Accepted differences found by the browser pass (task 09)

Task 10 must carry these into `standing-issues.md`; each was measured on ~40 pages against
`master` and judged not worth Blade churn to undo.

- **`space-y-*` and `divide-*` changed which sibling carries the spacing.** v3 put
  `margin-top` / `border-top` on every child but the first; v4 puts `margin-bottom` /
  `border-bottom` on every child but the last, inside `:where()`. Same rendering — verified
  element-by-element — but two consequences: computed-style comparisons look noisy, and a
  child's own `mt-*` now **wins** over its parent's `space-y-*` (zero specificity). One place
  in the app hits that: `codex/partials/fields.blade.php`'s `<div class="mt-6">` under
  `space-y-10` on `codex/edit` — 40px gap becomes 24px. Left alone because the same partial
  renders on `codex/create`, where 24px is already the v3 rendering.
- **Checked checkboxes/radios keep a `gray-300` border.** `@tailwindcss/forms` sets
  `border-color: transparent` on `:checked`; in v3 that out-specified `border-gray-300`, in v4
  the utilities layer beats the plugin's base layer regardless of specificity. Dropping the
  utility is not the fix — it would change the *unchecked* border to the plugin's gray-500.
- **`outline-hidden` computes as `outline-style: none`** where v3's `outline-none` computed as
  `2px solid transparent`. That is the intended v4 shape: the transparent outline is emitted
  inside `@media (forced-colors: active)`, so high-contrast users keep the affordance and
  everyone else sees no change. All focus **rings** (`box-shadow`) are byte-identical to
  `master` at rest, on focus and in the error state.
- **`rounded-full` is `calc(infinity * 1px)` (~3.4e38px) instead of 9999px** — identical pills.
- **Stock palette colours are OKLCH now** and a few resolve slightly differently in sRGB:
  `red-500/600`, `blue-600/800`, `green-800`, `gray-400`. Expected (plan bullet 3).
- **The desktop nav's active item is `navigation/dropdown-trigger`, which has no focus ring** —
  in v3 either. Task 06's `focus:ring-2` landed on `nav-link` / `responsive-nav-link`; on a
  1280px viewport those render only inside the responsive menu, so the ring is not visible in
  the top bar. Not a regression, but the accessibility gap task 06 closed for nav links is
  still open for dropdown triggers.

## Issues → resolutions

- **v4 auto-detection scans Markdown, so `.specs/` and `documentation/` were leaking utilities
  into the production stylesheet.** Found in the final sanity pass, after all ten tasks were
  done. v4 replaced v3's explicit `content` array with whole-project scanning, and it does not
  care whether a file can render HTML — a class name written in prose becomes a real rule.
  `.caret-transparent` and `.indent-8`, both merely *described* in this log as throwaway
  watcher probes, were shipping in the built CSS. Fixed with `@source not '../../.specs'` and
  `@source not '../../documentation'`, commented at the rule. Excluding beats narrowing to
  `resources/`: auto-detection is what makes a new Blade file work with no config.
  **This bites any doc that names a utility class** — the more the project documents its own
  CSS, the more phantom rules it ships.

- **The Docker polling watcher still drives Tailwind 4's scanner — no change needed.** Verified
  empirically on 2026-08-01 rather than assumed, because v4 scans for classes itself instead of
  reading a `content` array: a previously unused utility (`caret-transparent`) added to a Blade
  file appeared in the stylesheet the dev server served ~40s later, inside the 60s poll
  interval. `vite.config.js`'s comment now records the verification and its date; the polling
  block is unchanged, and `docker-compose.dev.yml` was not touched.
- **Task 08 stalled three times in an unbounded monitor loop and had to be finished by hand.**
  The implementing agent restarted the app container repeatedly, resetting its health-check
  clock, and each restart put it back to polling instead of concluding. It also left its probe
  markup (`<div class="indent-8" data-docker-watcher-probe>`) in `auth/login.blade.php`, which
  its own task file requires reverting. Both the probe and the loop were cleaned up externally.
  **Anyone re-running a task like this should bound the wait and report a blocker instead of
  polling indefinitely** — and grep for leftover probe markup before trusting a "reverted" claim.

- **A `git checkout -- resources/css/app.css` run while cleaning up a temporary test fixture
  during task 02 discarded task 01's entire uncommitted rewrite** (task 01's diff was never
  committed, per the "leave changes in the working tree" convention, so `checkout` silently
  reverted it to `HEAD`'s v3 file — no warning, since git has no way to distinguish "revert my
  scratch edit" from "revert the last real change" on an uncommitted file). Recovered by
  restoring `tailwind.config.js`/`postcss.config.js` from `HEAD`, installing
  `tailwindcss@3.4.19`/`postcss`/`autoprefixer` alongside v4 (temporarily pinning
  `package.json`'s `tailwindcss` to `3.4.19` — the upgrade tool refuses a version mismatch
  between `package.json` and `node_modules`), and re-running
  `npx @tailwindcss/upgrade --force`. The regenerated `app.css` reproduced the same diff stats,
  the same border shim, the same single surviving `theme('borderRadius.full')` at line 598,
  and the same dangling `--radius` reference as the pre-incident build — confirmed a faithful
  reconstruction rather than a fresh, differently-lossy migration. **Anyone touching this
  feature before the final commit should assume every task's changes are uncommitted and
  fragile**: a bulk `git checkout`/`restore`/`reset` anywhere in the tree can silently destroy
  a prior task's work with no error, because there is no commit to fall back to.

- **The codemod ran twice over `resources/`, shifting every affected utility one step too far
  down the v4 scale.** Root cause: the recovery above re-ran `npx @tailwindcss/upgrade --force`
  after only `app.css` and the config files had been reverted — the Blade templates still held
  the *first* run's output, so the renames applied a second time. `shadow-sm` → `shadow-xs` →
  `shadow-2xs` (66 usages), `shadow` → `shadow-sm` → `shadow-xs` (1), `rounded` → `rounded-sm`
  → `rounded-xs` (26). `rounded-sm` → `rounded-xs` (5) survived because `rounded-xs` has no
  next step, and idempotent renames (`outline-none` → `outline-hidden`, `flex-shrink-0` →
  `shrink-0`, arbitrary values → named scale) were unharmed. Every card, button, input and
  table in the app was rendering a lighter shadow and squarer corners than `master`; the green
  suite, the `var()` guard and `npm run build` all passed throughout. Fixed in task 09 by
  mapping each site back from the `master` token (93 corrections across 43 files).
- **`ring-opacity-5` silently became an opaque black ring.** v4 removed the `ring-opacity-*`
  utilities, so `ring-1 ring-black ring-opacity-5` on `dropdown` and `popover` painted a solid
  1px black hairline instead of a 5% one. Rewritten as `ring-black/5`. No codemod, test or
  build step flags a class name that no longer exists.
- **Page-header headings lost 2.5px of line-height on every app page.** `layouts/app.blade.php`
  styles them with `[&_h2]:text-sm` while the headings themselves carry `leading-tight`. In v3
  the descendant selector out-specified the utility, so `text-sm`'s 20px won; in v4 `leading-*`
  writes `--tw-leading` on the element itself, which always beats an ancestor's value, so the
  header band shrank 44px → 41.5px. Restored by stating `[&_h2]:leading-5` in the layout, with a
  comment at the class. This is the shape of every "specificity used to decide it" bug in v4:
  the fix is to say the value explicitly.
- **Verification method, for whoever re-runs this.** Screenshot-by-eye across ~40 pages misses
  1px shadow and 2px radius drift. What worked: serve `master` from a second `git worktree` on
  port 8001 (junction its `vendor`, point `.env` at the same SQLite file) alongside the branch
  on 8000, walk both with the same driver script dumping `getComputedStyle` for every element
  on every page, then diff the dumps with OKLCH→sRGB conversion so the palette re-notation
  collapses to noise and only real differences remain. That is what surfaced all three bugs
  above in one pass. Scripts are throwaway; the technique is not.
- **The dev Docker containers restarted mid-pass and re-created `public/hot`,** pointing `@vite`
  at a dead origin and re-binding port 8000 under the local server. Symptom: pages that had
  been loading built assets suddenly loaded from `:5173`. `docker compose -f
  docker-compose.dev.yml down` before a browser pass, and re-check `public/hot` if anything
  starts rendering unstyled.
