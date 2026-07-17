# Testing — Laravel 13 upgrade

No new automated tests are needed for this upgrade (no new behavior). The existing suite is the
regression guard; this file lists when/how to run it and what to check manually.

## Automated

* Run `composer test` (`config:clear` + `artisan test`) after **each** of the two bumps:
  1. Immediately after switching WAMP's active PHP to 8.3+, still on Laravel 12 — isolates
     PHP-only deprecations (e.g. removed dynamic properties, changed implicit-nullable-parameter
     behavior) from anything the framework bump introduces.
  2. Again after `composer require laravel/framework:^13.0` and resolving the upgrade guide's
     required config changes.
* Full suite must stay green both times — `tests/Unit/SpecsStatusConsistencyTest` included,
  since it's unrelated to this change and any failure there would indicate a stray regression.
* Watch specifically for framework-version-sensitive suites: anything touching validation
  messages, `Rule::enum(...)` (`ValidMarkdown` and other custom rules in `app/Rules`), and the
  policy/authorization tests (`SceneTest`, `ActTest`, `ChapterTest`, `StoryTest`) — these are the
  most likely to trip on a Laravel-internals behavior change.

## Manual smoke test (after both bumps, before calling it done)

Since this touches the runtime every request goes through, exercise the app once by hand (or via
the `run-imagoldfish` skill) rather than trusting the test suite alone:

1. Log in.
2. Open a project's story overview (act → chapter → scene tree) — this is the nested eager-load
   path called out in `CLAUDE.md`; a Laravel bump that changed Eloquent eager-loading behavior
   would show up here as an N+1 explosion or missing data.
3. Edit and save a scene (exercises Form Requests, policies, and the markdown rule).
4. Export an epub (exercises `rampmaster/phpepub`, the third-party package most likely to be
   PHP-8.3-sensitive).
5. Check `/robots.txt` still renders (dynamic route, unrelated to static files, but cheap to
   confirm the routing layer didn't regress).

## Rollback signal

If `composer test` fails after the PHP switch (step 1) and the failure isn't a simple deprecation
notice, stop before touching `composer.json` — fix or understand the PHP-only failure first so
the framework bump isn't debugging two unknowns at once.
