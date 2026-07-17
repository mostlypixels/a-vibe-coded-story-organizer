# Hidden from crawlers — Overview

## Problem statement

The application must be able to hide itself from crawlers, scrapers and search
engines. Hiding is a **site-wide** concern (one `robots.txt`, one meta-robots
policy), not per-project or per-user. It ships **on by default** ("hidden").

Two enforcement surfaces:

1. **`/robots.txt`** — instructs well-behaved crawlers not to crawl.
2. **`<meta name="robots" content="noindex">`** — instructs search engines not
   to index the pages they can reach.

Plus an in-app configuration surface to toggle hidden mode and maintain a
whitelist of user-agent terms that stay allowed even while hidden.

## Goals

- A single, app-wide setting toggling "hidden mode" (default: **hidden/on**).
- When hidden: `/robots.txt` disallows all crawlers **except** whitelisted
  user-agent terms; every public-facing page carries a `noindex` meta robots tag.
- When not hidden: `/robots.txt` allows all; no `noindex` tag is forced on
  ordinary pages (link-only pages such as shared scenes stay `noindex`
  regardless — that is an existing, separate privacy rule).
- An in-app settings screen (any authenticated user) to flip the toggle and edit
  the whitelist. `/robots.txt` reflects the settings **live** (dynamic route).

## Non-goals

- No admin **role** / `is_admin` column. Decision: any authenticated user may
  edit the global settings (single-operator tool). See `open-questions.md`.
- No physical regenerate-to-disk step / artisan command. Decision: `/robots.txt`
  is a dynamic route rendered from settings, so it is always in sync. See
  `open-questions.md`.
- No per-crawler crawl-delay, sitemap directives, or path-level allow/deny
  granularity — whitelist entries allow the **whole** site; everyone else is
  blocked from the **whole** site.
- Not a defence against malicious bots that ignore `robots.txt`/meta tags (the
  referenced bad-bot-blocker list is server/WAF territory, out of app scope).

## Decisions already taken (from the requester)

- **Access model:** any authenticated user manages the global crawler settings
  (no new role).
- **robots.txt delivery:** dynamic route at `/robots.txt` rendered live from the
  settings; the static `public/robots.txt` is removed so the route wins.

## User stories

- As the site operator, the app is hidden from search engines by default, so an
  unfinished project is not indexed before I choose to publish.
- As the site operator, I can open a settings page and turn hidden mode off when
  I am ready to be discoverable.
- As the site operator, I can whitelist a crawler by a term in its user agent
  (e.g. `Googlebot`) so it may crawl even while the site is otherwise hidden.
- As a search engine, fetching `/robots.txt` I receive rules that match the
  current settings without any manual regeneration.

## Acceptance criteria

1. Fresh install (no settings row) behaves as **hidden**: `/robots.txt` returns
   `User-agent: *` / `Disallow: /`, and public pages carry `noindex`.
2. `/robots.txt` is served by the app (dynamic), `text/plain`, HTTP 200, for
   unauthenticated visitors. No static `public/robots.txt` remains.
3. With hidden on and whitelist `["Googlebot"]`, `/robots.txt` contains a
   `User-agent: Googlebot` group with an empty `Disallow:` (allowed) **and** a
   `User-agent: *` group with `Disallow: /` (blocked).
4. With hidden **off**, `/robots.txt` contains `User-agent: *` with an empty
   `Disallow:` and no site-wide block; ordinary public pages carry no forced
   `noindex`.
5. The meta-robots tag is present and `noindex` on public-facing layouts exactly
   when hidden mode is on (shared-scene pages remain `noindex` unconditionally).
6. An authenticated user can view and update the settings (toggle + whitelist);
   a guest is redirected to login. Invalid whitelist terms fail validation.
7. Full existing test suite stays green; new feature tests cover the above.
