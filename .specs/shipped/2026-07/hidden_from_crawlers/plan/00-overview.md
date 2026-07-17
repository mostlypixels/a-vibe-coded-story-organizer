# Hidden from crawlers — Plan overview

This is the manual for the plan. It is never implemented or moved. Read it, plus
`.specs/hidden_from_crawlers/expanded/`, before starting any task.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-data-model-and-config.md` | Singleton `crawler_settings` table, `CrawlerSetting` model, `config/crawlers.php`. Foundation for every other task. |
| 02 | `02-robots-txt-route.md` | `RobotsTxtGenerator` service + public dynamic `/robots.txt` route; remove the static `public/robots.txt`. |
| 03 | `03-meta-robots-component.md` | `x-robots-meta` Blade component wired into all four layouts (forced on `public`). |
| 04 | `04-settings-screen.md` | Authenticated settings page: controller, Form Request, view, routes, nav links. |
| 05 | `05-documentation.md` | Update `documentation/architecture.md`, `CLAUDE.md`, `CHANGELOG.md`. |

Dependency graph: 01 → {02, 03}; 04 depends on 01 + 02 (its end-to-end test hits
the real `/robots.txt`); 05 depends on all.

## Binding design decisions (settled in the grill — do not re-litigate)

1. **Access model: any authenticated user** manages the global settings. No
   `is_admin` role. `auth` middleware + `authorize()` returns `$this->user() !== null`.
2. **`/robots.txt` is a dynamic route** rendered live from settings. The static
   `public/robots.txt` is **deleted** so the route is reached.
3. **Whitelist = product tokens**, robots.txt only. Each term emits a
   `User-agent: <term>` allow-group. No request-layer / WAF UA matching (the spec's
   "terms contained in the user agent" is delivered as robots.txt product-token
   groups, which is the only thing robots.txt can express).
4. **Meta tag content is `noindex, nofollow`** (one string), matching the existing
   `public.blade.php`. Wired into **all four** layouts: `app`, `guest`, `welcome`
   (toggle-governed) and `public` (`:force="true"`, always hidden — shared scenes
   are private-by-link).
5. **Whole-site allow/block only.** No per-path rules, no `Crawl-delay:`, no
   `Sitemap:` line.
6. **Whitelist storage = JSON column + textarea** (one term per line). No separate
   `crawler_whitelist_entries` table.
7. **Default hidden lives in two places** by design: the DB column default and
   `config('crawlers.default_enabled')`. Keep both, comment why.
8. **Out of scope:** request-layer bot blocking, firewall/UA denylist, sitemap
   generation. This feature is advisory (robots.txt + meta tags) only.

## Core invariants every task must preserve

- **Singleton:** exactly one `crawler_settings` row ever exists. Always read via
  `CrawlerSetting::current()`, which lazily creates it from config defaults. Never
  `new CrawlerSetting` / second row.
- **Authorization exception:** `CrawlerSetting` is **global** — it has no owning
  `Project`, so it does **not** use `ProjectPolicy`'s walk. This is the single
  deliberate departure from the project-scoped authorization convention and must
  be documented (task 05) so nobody "fixes" it into a project walk.
- **robots.txt well-formedness:** whitelist terms are validated line-safe (no
  `\r`, `\n`, `:`, `#`) on the write path; the generator trusts that guard.
- **Default is hidden:** a fresh install with no settings row must behave as
  hidden (robots blocks all, pages carry `noindex`).
- **Public route stays public:** `/robots.txt` lives outside the `auth` group,
  alongside `shared.scenes.show` — do not widen or move the auth group.
- Follow existing conventions: thin controllers, logic in the service/Form
  Request, config for constants, feature tests in `ProjectTest.php` style.
