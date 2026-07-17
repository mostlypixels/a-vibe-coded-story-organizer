# Admin Configuration — Overview

## Problem statement

Today the only global, non-project setting in the app is **Site settings** — the
`CrawlerSetting` singleton, reached from the user dropdown at `GET /settings/crawlers`
(`resources/views/settings/crawlers/edit.blade.php`). There is no home for other
application-wide configuration, and no shared shell to hang it on.

This feature introduces an **Admin Configuration** area: a dedicated screen with a
**left sidebar** listing configuration sections, and a content pane on the right. The
existing search-engine settings move into it (they are the first section), and three new
sections are stubbed or built:

1. **Settings** (General settings) — hosts the existing search-engine visibility form under
   a new page heading "General settings".
2. **Appearance and accessibility** — graphical/accessibility options later; **empty
   placeholder** for now.
3. **Export and import** — a page with two tabs (**Export**, **Import**).
4. **Database configuration** — switch between database backends and convert SQLite ⇄ MySQL.

> [!IMPORTANT]
> Concerns 3 and 4 are an order of magnitude larger and riskier than 1 and 2. The source
> spec describes them in one line each, but "download/restore all data" and "convert
> SQLite to MySQL from a web button" are each a substantial, data-loss-sensitive feature.
> This expansion **builds the shell + sections 1 and 2 fully**, and treats **export/import
> and database configuration as scoped-down or deferred** pending the answers in
> [`open-questions.md`](open-questions.md). Do not let the plan quietly commit to a live
> cross-driver migration engine.

## Goals

- A reusable **admin layout** (sidebar + content pane) that every configuration section
  renders inside, with active-state highlighting matching the existing nav convention
  (`documentation/architecture.md` → *Navigation active state*).
- Move the search-engine settings under **General settings** with **no loss of behaviour**
  (the robots.txt toggle, whitelist, preview link, and "Saved." flash all still work).
- Stub **Appearance and accessibility** as an empty, titled placeholder page so the sidebar
  is complete and future work has a landing spot.
- A single, discoverable entry point in the top nav (the current "Site settings" dropdown
  link becomes the admin entry).
- Establish the **authorization posture** for the admin area explicitly (see open questions)
  rather than inheriting the crawler exception by accident.

## Non-goals (v1)

- **No `is_admin` role / users table change** unless [`open-questions.md`](open-questions.md)
  Q1 decides otherwise. The app currently has no roles; the crawler setting is editable by
  *any* authenticated user by deliberate design.
- **No real Appearance/accessibility options** — placeholder page only.
- **No live cross-driver data migration** (SQLite→MySQL row copy) in v1 unless Q4 says
  otherwise. See the database-configuration open question for why this is dangerous.
- **No project-level or partial export UI** beyond whatever single format Q3 settles on.
- No new theming system, no `.env` editor UI beyond what Q4 explicitly approves.

## User stories

- *As the site owner*, I open **Admin → General settings** and toggle search-engine
  visibility exactly as I do today, but now inside a configuration area with a sidebar.
- *As the site owner*, I see **Appearance and accessibility** in the sidebar and understand
  it's coming soon (empty page with a heading and a short "coming soon" note).
- *As the site owner*, I open **Export and import**, switch between the Export and Import
  tabs, and (scope per Q3) download a backup / restore one.
- *As the site owner*, I open **Database configuration** and see my current database backend
  and guidance (scope per Q4).

## Acceptance criteria (v1, assuming recommended answers)

- `GET /admin` redirects to the first section (General settings) and every admin page renders
  the sidebar with the current section highlighted (`aria-current="page"`, matching the nav
  active-state test convention).
- The General settings page shows the search-engine form; saving it still updates
  `CrawlerSetting::current()` and the "Saved." flash appears — a feature test equivalent to
  the current crawler test passes against the new route.
- The Appearance page returns `200` with its heading and no form.
- The top-nav "Site settings" link is replaced by an admin entry pointing at `/admin`.
- Every admin route is behind `auth` and the (single) admin-access check chosen in Q1; a
  guest is redirected to login. If Q1 keeps "any authenticated user", that is asserted in a
  test and documented as the deliberate continuation of the `CrawlerSetting` exception.
- Export/Import and Database sections meet whatever **reduced** acceptance criteria Q3/Q4
  fix (e.g. "Export downloads a full JSON+media zip"; "Database page is read-only and shows
  the active connection"). No criterion in v1 requires an automated SQLite→MySQL conversion
  unless Q4 explicitly approves and scopes it.
