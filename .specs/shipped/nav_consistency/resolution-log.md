# Nav consistency — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

- **Scope = all three desktop dropdowns** (Story + Codex + Timeline), not Story-only. The
  spec's premise ("Codex already highlights") was true only of the mobile menu; the desktop
  dropdowns highlighted nothing. (Open question Q1 — user chose "all three".)
- **Trigger highlighting is in scope** — collapsed triggers reflect the active section using
  `x-nav-link`'s active look. (Q2 — user chose "items + trigger".)
- **Item active style** = `bg-aqua-50 text-navy-900 font-semibold`, no left accent bar; non-active
  branch byte-identical to today. (Q3, best-option pick.)
- **Matchers consolidated** into one `@php` boolean block shared by desktop triggers, desktop
  items, and the responsive menu — no `Nav` class / view composer. (Q4, best-option pick.)
- **New `tests/Feature/NavigationTest.php`**; assert on `aria-current="page"`, not Tailwind
  classes. (Q5.)
- Remaining grill questions were answered "bypass, pick best option" by the user.
- **Task 03 — doc placement.** The nav note went into `documentation/architecture.md` as a new
  `## Navigation active state` section (before "Where things live"), since the nav had no existing
  dedicated home in either `architecture.md` or `code-style.md`. `code-style.md` keeps only the
  generic "reuse a component before creating one" Blade rule; the section-specific matcher/`@php`
  convention is architectural, so it belongs in `architecture.md`.

## Deviations from the spec/plan

- **Task 01 — Story trigger markup.** Rather than swapping the whole class string, the
  trigger keeps `border-b-2` static and interpolates only the state-dependent tokens
  (`text-white border-flame-500` when `$storyActive`, else `text-aqua-100 border-transparent`).
  Same rendered result as the spec's `x-nav-link` active look; less duplication. Task 02
  should follow the same shape for the Codex/Timeline triggers.
- **Task 02 — none.** Codex/Timeline triggers follow Task 01's trigger shape exactly
  (interpolate only the state tokens, `border-b-2` static). The responsive Codex loop
  already used the enum-aware `route('type') === … || route('codexEntry')?->type === …`
  expression from Task 01's file state, so it needed no re-pointing — it is already the
  single loop expression the desktop dropdown now mirrors. Desktop and responsive `@php`
  blocks were extended identically with the Timeline/Codex booleans.

## Issues → resolutions

- **Task 01 — trigger has no semantic hook.** The dropdown trigger is a `<button>`, not a
  link, so it carries no `aria-current`. The trigger test therefore asserts on the active
  class token (`text-white border-flame-500`) — the one sanctioned exception to
  "assert on `aria-current`, not Tailwind classes" (the task file explicitly allows it).
  The token is stable because its class order (`… text-white border-flame-500 …`) is unique
  to the trigger; the active `x-nav-link` (Home) emits the same two classes but non-adjacent,
  so `assertDontSee('text-white border-flame-500')` on Home is not a false positive.
- **Task 01 — `assertDontSee` scoping on Home.** The breadcrumb component renders
  `<span aria-current="page">` on its last crump, so a bare `aria-current="page"` substring
  check would fail the "no dropdown item marked on Home" test. Resolution: the negative
  assertion is scoped to anchors via regex (`/<a[^>]*aria-current="page"/`), which the
  breadcrumb `<span>` does not match.
- **Task 02 — a bare trigger-token `assertSee` can't identify which trigger is active.**
  Task 01's Story-trigger test used `assertSee('text-white border-flame-500')`, which works
  only because on Home *no* trigger is active. For Codex-vs-Story that token appears for
  whichever section is active, so it can't distinguish them. Root cause: the trigger is a
  `<button>` with no per-section semantic hook. Resolution: added
  `assertTriggerIsActive`/`assertTriggerIsNotActive` helpers whose regex ties the active
  token to a specific labeled button (`/<button[^>]*text-white border-flame-500[^>]*>\s*Codex/`),
  so "Codex active, Story not" on a Codex page is asserted unambiguously.
- **Task 02 — runtime-surface check.** Verified beyond the green suite: `npm run build`
  succeeds, no `public/hot` (app serves the build), and all reused active tokens
  (`border-flame-500`, `bg-aqua-50`, `text-navy-900`, `text-white`) are present in the
  compiled `public/build/assets/app-*.css`. Task 02 introduced no new class tokens.
