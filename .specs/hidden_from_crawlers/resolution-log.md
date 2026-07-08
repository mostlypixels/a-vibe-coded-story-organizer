# Hidden from crawlers — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

- **Access model:** any authenticated user manages the global crawler settings; no
  `is_admin` role.
- **robots.txt delivery:** dynamic route rendered live from settings; static
  `public/robots.txt` removed.
- **Whitelist semantics:** product-token allow-groups in robots.txt only — the
  spec's "terms contained in the user agent" is delivered as `User-agent: <term>`
  groups (the only thing robots.txt can express). No request-layer UA matching.
- **Meta tag content:** `noindex, nofollow` (single string), on all four layouts;
  `public` layout forced.
- **Granularity:** whole-site allow/block; no `Crawl-delay`/`Sitemap`/per-path.
- **Whitelist storage:** JSON column + textarea (one term per line); no separate
  table.
- **Default hidden** duplicated intentionally in the DB column default and
  `config('crawlers.default_enabled')`.
- **Out of scope:** request-layer bot blocking / firewall / UA denylist / sitemap
  generation.

## Deviations from the spec/plan

- **None material.** All five tasks implemented as specified (singleton + config,
  dynamic `/robots.txt` + generator with the static file removed, `x-robots-meta`
  on all four layouts, authenticated settings screen + nav, docs).

## Issues → resolutions

- **Stock `ExampleTest` 500'd after wiring the meta component into `welcome`.**
  `<x-robots-meta>` reads `CrawlerSetting::current()`, which queries
  `crawler_settings`. `tests/Feature/ExampleTest.php` (the Breeze smoke test that
  hits `/`) did **not** `use RefreshDatabase`, so the table was absent and the page
  threw "no such table: crawler_settings". **Resolution:** added
  `use RefreshDatabase` to `ExampleTest` (with a comment explaining why). Lesson:
  every public-facing layout now performs a DB read on render — any test that hits
  a page needs a migrated database. Full suite green: **229 passed**.
