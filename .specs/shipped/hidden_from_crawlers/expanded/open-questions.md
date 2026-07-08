# Hidden from crawlers — Open questions

Each has a recommended answer. Two were already settled by the requester and are
recorded as resolved so the grill can confirm, not relitigate.

## Resolved (confirm only)

### Q1. Who may edit the global crawler settings?
**Resolved: any authenticated user** (no `is_admin` role). This is a
single-operator tool; a role adds a migration + a way to promote a user for
little gain. Trade-off: no privilege separation if the deployment ever has
multiple users. Alternative declined: add `is_admin` boolean + a Gate.
→ Grill: is multi-user with privilege separation a near-term need? If yes,
revisit before shipping (cheaper now than retrofitting).

### Q2. How is `/robots.txt` delivered?
**Resolved: dynamic route**, rendered live from settings; static
`public/robots.txt` removed. Always in sync, no regenerate step. Trade-off: a
tiny per-request query + the file no longer visible on disk.
→ Grill: does any deploy step or CDN cache `/robots.txt` such that "live" is
actually stale? If a CDN fronts it, we may want a short cache header.

## Open (need a decision)

### Q3. Meta tag content: `noindex` vs `noindex, nofollow`?
**Recommend `noindex, nofollow`** to match the existing `public.blade.php` value
and keep one string. The spec literally says "set to `noindex`" — `noindex,
nofollow` is a strict superset (also stops link-following). Accept the superset,
or honour the spec literally with bare `noindex`?

### Q4. Which pages get the `noindex` tag?
**Recommend:** all public-facing layouts — `welcome`, `guest` (auth screens),
`public` (shared scenes, forced). App pages (`layouts/app`) are behind auth so
crawlers can't reach them; adding the tag there is harmless and uniform —
**recommend adding it anyway** for consistency. Agree, or scope strictly to
crawler-reachable pages only?

### Q5. Whitelist granularity.
**Recommend:** whitelisted term → allowed to crawl the **entire** site; everyone
else blocked from the **entire** site. No per-path rules, no `Crawl-delay`, no
`Sitemap:` line. Sufficient for the spec? (Sitemap directive is a plausible later
add once the site is public.)

### Q6. Whitelist matching semantics in robots.txt.
A term becomes a `User-agent: <term>` group. Real crawlers match the `User-agent`
line against their product token (case-insensitive, longest-match wins).
**Recommend** documenting that terms should be product tokens (e.g. `Googlebot`,
`Bingbot`) rather than arbitrary UA substrings, since robots.txt cannot match an
arbitrary substring of the full UA string the way a server-side UA filter could.
Is a true "substring of the user agent" filter expected (which robots.txt alone
**cannot** express), or is the product-token convention acceptable?

### Q7. Config default duplication.
Default-hidden lives in both the column default and `config/crawlers.php`.
**Recommend** keeping both (config = documented source of truth, column = insert
backstop) and noting it. Acceptable, or collapse to one?

### Q8. Should the whitelist also drive anything beyond robots.txt?
The spec's reference link (bad-bot-blocker) hints at server-level UA blocking.
**Recommend explicit non-goal:** this feature only emits robots.txt + meta tags
(advisory); it does **not** block bad actors at the request layer. Confirm that
active UA blocking / firewalling is out of scope for this spec.
