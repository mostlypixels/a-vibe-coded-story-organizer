# Admin Configuration — Open Questions

Each question states a **recommended answer**. These feed the `grilling` pass before the plan
is decomposed — Q3 and Q4 in particular decide whether this stays a small feature or balloons
into three.

## Q1 — Who may access the admin area? (blocking)

The app has **no `is_admin` role**; `CrawlerSetting` is deliberately editable by *any*
authenticated user (`documentation/architecture.md` → *Hidden from crawlers*). The admin area
now also fronts export/import and (potentially) database operations, which are far more
destructive than a robots toggle.

**Recommended:** for v1 keep the existing posture — **any authenticated user** — but encode it
**once** as a single `access-admin` Gate/middleware on the `admin.` route group (not re-checked
per controller), so tightening to a role later is a one-line change. Explicitly document it as
the continued `CrawlerSetting` exception.

- Is the app single-user in practice today? If it may become multi-user, "any authenticated
  user can export all data / reconfigure the database" is a real exposure — do we add an
  `is_admin` flag on `users` now instead?
- If we add a role: who is the first admin (seed? first registered user? a config value?)?

## Q2 — What happens to the current `/settings/crawlers` route? (blocking, small)

The search-engine form is live at `crawler-settings.edit` / `crawler-settings.update`, linked
from the user dropdown.

**Recommended:** **rename** the controller to `GeneralSettingsController` and the routes to
`admin.settings.*`, delete the old `crawler-settings.*` routes, and update the two nav links +
the one view wrapper. The form fields and `UpdateCrawlerSettingRequest` stay byte-for-byte.
(Keep `/robots.txt` and `RobotsTxtController` untouched.)

- Any external bookmarks/links to `/settings/crawlers` we must preserve with a redirect?

## Q3 — Export / Import: what scope, and is it v1 or its own spec? (blocking, large)

The source spec says only "Export / Import" with two tabs. That hides a large design:

- **What is exported?** Whole site (all users' data)? The current user's projects? A single
  selected project?
- **Format?** JSON document, `.zip` (JSON + media files), a raw SQLite file, a SQL dump?
- **Does it include media files** (`codex_media` on disk)? (If yes, it must be a zip, not JSON.)
- **Import semantics:** *replace* everything (destructive — can wipe the site) or *merge/add*?
  Replace needs an explicit confirmation and a transaction.

**Recommended:** scope v1 to a **full data backup**: export a `.zip` of a versioned `data.json`
(the User→Project aggregate + Codex) **plus the media files**; import is **additive/restore**
into the current user (never a blind truncate), routed through models/services so the documented
invariants survive (main plotline, `position`, attribute-timeline baseline, rich-text
sanitisation — see `data-model.md`). If that is too big for this feature, **split Export/Import
into its own spec** and ship the shell with the tab page stubbed ("coming soon"), exactly like
Appearance.

- Which is it: build the backup now, or stub the tabs and spec it separately?

## Q4 — Database configuration: build live conversion, or read-only for now? (blocking, large + risky)

The source spec asks to "switch between different databases, and convert SQLite⇄MySQL from the
UI." Doing this from a synchronous web request is genuinely dangerous (see
`architecture.md` → *Section 4*): it rewrites `.env`, migrates a fresh target, streams every row
across drivers with differing type/JSON/auto-increment semantics, and risks leaving the app
pointing at an empty or half-migrated database — all while serving the triggering request. It's
maintained by junior devs.

**Recommended:** v1 = **read-only** page showing the active connection (driver, database
name/path, host — never the password) plus guidance that switching/converting is a **CLI/ops**
task. Defer any actual conversion to its **own spec**, implemented as an **Artisan command**
(`php artisan db:convert …`) run with the site in maintenance mode, never a controller action.

- Do you accept read-only for v1, or is live conversion a hard requirement now (if so, it must
  be its own spec and cannot be a click a junior dev maintains)?

## Q5 — Tabs implementation for Export/Import (small)

There is no `x-tabs` component today.

**Recommended:** inline Alpine (`x-data="{ tab: 'export' }"`) with proper
`role="tablist"`/`tab`/`tabpanel` + keyboard nav, per "no abstraction before a second caller".
Extract an `x-tabs` component only when a second screen needs tabs. Agree?

## Q6 — Nav entry-point label (trivial)

The user-dropdown "Site settings" link becomes the admin entry.

**Recommended:** relabel to **"Configuration"** pointing at `route('admin.index')` (which lands
on General settings). Prefer "Configuration", "Admin", or keep "Settings"?
