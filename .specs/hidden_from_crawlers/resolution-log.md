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

_None yet._

## Issues → resolutions

_None yet._
