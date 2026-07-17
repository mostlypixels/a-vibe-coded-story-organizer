# Laravel 13 upgrade — plan overview

Maintenance upgrade, no behavior change. Two stacked bumps: WAMP's active PHP, then the
`laravel/framework` composer constraint. No new controllers/models/routes.

## Binding decisions (from the grill — do not re-litigate)

* **Target PHP is 8.5**, not the minimum 8.3. Build already exists at
  `C:\wamp64\bin\php\php8.5.7`. The repo's CLI PATH already resolves `php` to 8.5.7; only
  Apache's `httpd.conf` (`LoadModule php_module ... php8.2.18\php8apache2_4.dll`) still points at
  8.2.18 — that's the actual switch to make.
* **The agent edits `httpd.conf` directly** (outside the repo, in
  `C:\wamp64\bin\apache\apache2.4.59\conf\httpd.conf`) and restarts the Apache service itself
  (WAMP's `wampmanager` tray, or `net stop wampapache64` / `net start wampapache64`) — this is
  not left as a manual step for the user, per explicit confirmation during planning.
* **`composer.json` targets `"php": "^8.5"` and `"laravel/framework": "^13.0"`.**
* `composer why-not laravel/framework 13.20.0` (run during planning) showed the only blockers are
  this project's own `laravel/laravel` pin (`^12.0`, i.e. `composer.json` itself — expected, that's
  what task 3 changes) and `laravel/tinker` v2.11.1 (caps at `illuminate/* ^12.0`). No blocker from
  `ezyang/htmlpurifier`, `rampmaster/phpepub`, or `secondnetwork/blade-tabler-icons` — don't
  re-run that check unless the framework `composer require` itself reports a new conflict.
  `laravel/tinker` does **not** need a manual bump — running
  `composer require laravel/framework:^13.0` lets Composer resolve tinker to a compatible version
  in the same operation.
* **No proactive bump** of `laravel/breeze`, `laravel/sail`, `laravel/pail`, `laravel/pint`,
  `nunomaduro/collision` — only if Composer's resolution forces it.
* No CI to update (`.github/workflows/` doesn't exist) and no new automated tests are needed —
  the existing suite is the regression guard (see `expanded/testing.md`).

## Invariants every task must preserve

* `composer test` (`artisan config:clear` + `artisan test`) must be green after **each** task
  that touches the PHP or framework version — not just at the end. A failing task should stop
  and be understood before the next task starts (don't debug two unknowns — PHP vs. framework —
  at once).
* No app behavior change. If the upgrade guide requires a config-file change (e.g. a renamed
  driver key), match the existing customized value's *intent*, don't blindly copy the framework's
  new default.

## Execution order

1. **`01-switch-wamp-php.md`** — Switch WAMP's active Apache PHP module to 8.5.7 and verify the
   app still runs correctly on Laravel 12 under the new PHP version.
2. **`02-verify-php-extensions.md`** — Confirm the PHP 8.5 build has the extensions the app
   requires (`ext-intl`, `ext-zip`, plus whatever `composer test` / manual smoke surfaces) and
   fix `php.ini` if anything is missing, still on Laravel 12.
3. **`03-bump-composer-constraints.md`** — Bump `composer.json`'s `"php"` and
   `"laravel/framework"` constraints and run `composer update`, resolving whatever the Laravel 13
   upgrade guide requires in `config/*.php` / `bootstrap/app.php`.
4. **`04-manual-smoke-test.md`** — Manual smoke test of the app's core flows on the fully-upgraded
   stack (this one is a verification task, not a code task — see `expanded/testing.md`).
5. **`05-update-docs.md`** — Update `documentation/architecture.md`, `README.md`/`melusine.md` if
   they mention the version, and add the `CHANGELOG.md` `Unreleased` → `Changed` entry.

Tasks 1–2 must land (and `composer test` stay green) before task 3 starts, so any PHP-only
breakage is isolated from the framework bump. Task 4 depends on task 3. Task 5 can run any time
after task 3 confirms the target versions, but is sequenced last so the documented version
matches what actually landed.
