# Admin Configuration — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

Decided in the pre-decomposition grill (`grilling` over `expanded/open-questions.md`):

- **Q1 — Admin access:** any authenticated user, via a single `access-admin` Gate on the
  `/admin` group. No `is_admin` role. Deliberate continuation of the `CrawlerSetting`
  any-authenticated-user exception, kept in one place to tighten later.
- **Q2 — Old crawler route:** rename and **delete** `crawler-settings.*` (→ `admin.settings.*`);
  no redirect alias.
- **Q3 — Export/Import:** **stub only** for v1 — page + two tabs + "coming soon". The real
  backup/restore engine is deferred to its own future spec.
- **Q4 — Database configuration:** **read-only** display of the active connection, and **no**
  explanatory/CLI note in the UI (that rationale stays in the spec docs).
- **Q5 — Tabs:** inline Alpine with ARIA roles + keyboard nav; no reusable `x-tabs` component.
- **Q6 — Nav label:** the user-dropdown entry reads "Configuration", landing on `admin.index`.
- **Knock-on:** Export/Import keeps a short "coming soon" body (an empty tab looks broken);
  the Database page has no such note because it shows real content (the connection).

## Deviations from the spec/plan

### Task 01 — admin shell

- **Section stub views set their own `header` slot to "Configuration".** The plan/ui doc
  describes the page header loosely ("Configuration" or the section name). All four section
  views forward a `<x-slot name="header">Configuration</x-slot>` for a consistent top band;
  `<x-admin-layout>` only forwards the slot if a section supplies it (optional).

### Task 02 — general settings

- **Where the migrated coverage landed.** The retired screen's behavioural tests (renders,
  save, unsafe-term rejection) moved from `CrawlerSettingTest` into `AdminConfigurationTest`
  (under a "General settings (task 02)" section), retargeted to `admin.settings.*`.
  `CrawlerSettingTest` keeps only its model / `robots.txt` / meta-tag coverage (and lost its
  now-unused `User` import). This keeps the crawler *singleton* tests with the model and the
  *admin screen* tests with the admin shell, rather than splitting one concern across files.

### Task 03 — export/import stub

- **No `x-cloak` infrastructure existed, so the hidden tab panel could flash on load.** Alpine
  is loaded globally (`resources/js/app.js`) but no view had used it before, and there is no
  `[x-cloak]{display:none}` rule anywhere. **Resolution instead of adding a global x-cloak
  rule** (out of this task's scope): the initially-inactive Import panel is rendered with an
  inline `style="display: none;"`, which Alpine's `x-show` then takes over. No flash, no change
  to the shared layout. The panel text is still in the server HTML (only visually hidden), so
  the "both coming soon panels present" test asserts on the rendered markup unaffected.

## Issues → resolutions

### Final pass (ship-plan) — interactive tab surface not driven live

- **The Export/Import Alpine tab switching was verified structurally, not driven in a browser.**
  No browser automation was available in-session. What *was* confirmed: `composer test` green
  (275 passed, 916 assertions); a fresh `npm run build` succeeds; no `public/hot` (so `@vite`
  serves the build, not a dead dev server); and the runtime-swapped `:class` tokens
  (`border-flame-500`, `ring-ocean-500`, `text-navy-900`, `bg-aqua-50`) are present in the built
  `public/build/assets/app-*.css`, so Tailwind's scanner picked them up from the `:class`
  literals. **Left for the user to confirm by click-path** at `/admin/data` (authenticated):
  (1) Export tab shows by default; (2) clicking Import swaps panels; (3) Left/Right arrows move
  focus+selection between tabs; (4) Tab key enters only the active tab (roving tabindex); (5) a
  focus ring is visible on the focused tab.

### Task 04 — database read-only

- **The password-leak test cannot switch `database.default`.** The obvious approach the task
  sketches — define a fake `mysql` connection with a known password and set it as the default —
  breaks the run two ways that a first pass hides:
  1. **RefreshDatabase transaction cascade.** RefreshDatabase begins its transaction on the
     default connection at setUp and rolls back whatever is default at teardown. Leaving `fake`
     as default at teardown leaves the real sqlite transaction open → *every* later test fails
     with `PDOException: There is already an active transaction` (a 245-test cascade the first
     time).
  2. **Live render needs the default connection.** Even with the default restored in `finally`,
     the page render itself queries the default connection — `x-app-layout` includes
     `x-robots-meta`, which calls `CrawlerSetting::current()`. Pointed at an unreachable fake
     host, the request 500s (`Connection refused`) instead of exercising the whitelist.
  **Resolution:** don't switch the default at all. Inject the known `password`/`username` into
  the **active** (sqlite) connection's config and assert the rendered HTML omits them. The
  controller's whitelist (driver / database / host only) is exactly what must drop the secret,
  so this tests invariant 5 directly while the app keeps rendering through the real connection.
  Verified independently via `artisan tinker`: with `sqlite.password` set, the rendered `<dl>`
  shows driver + database path, omits Host, and contains no trace of the password.

### Task 01 — admin shell

- **"No form/input on the Appearance page" can't be asserted on the whole HTML.** The shared
  `x-app-layout` nav always ships a logout `<form>` plus the `@csrf` hidden `<input>`, so a
  naive `assertDontSee('<form')` on the full response fails. **Resolution:** scope that
  assertion to the page's `<main>…</main>` region (extracted by regex) — the logout form lives
  in the nav, outside `<main>`, so the section's own emptiness is what's tested. A green suite
  would otherwise have hidden a false assumption about the layout.
