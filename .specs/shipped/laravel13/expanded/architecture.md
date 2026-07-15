# Architecture — PHP/Laravel bump mechanics

No app architecture changes (no new controllers, models, routes, or policies). This file tracks
the moving parts of the upgrade itself.

## Runtime (WAMP / PHP)

* WAMP tray → PHP → pick the 8.3+ build already present alongside the current 8.2.18 install.
  Confirm with `php -v` on a fresh shell after switching (PATH / WAMP's `wampmanager` sets the
  active `php.exe`).
* Re-check `php.ini` extensions carried over match what the app needs: `ext-intl`, `ext-zip`
  (both explicit `composer.json` requires) plus whatever WAMP enables by default (`pdo_sqlite`,
  `mbstring`, `openssl`, `curl`, `fileinfo`, `gd` if used by epub/export code).
* No code change is required for this step alone — Laravel 12 already supports PHP 8.3/8.4.

## Composer / framework

* `composer.json`:
  * `"php": "^8.2"` → `"^8.3"`.
  * `"laravel/framework": "^12.0"` → `"^13.0"`.
* Run `composer update laravel/framework` (or `composer require laravel/framework:^13.0`) and
  let Composer resolve the transitive graph — expect Symfony components to move to 7.4/8.0 and
  possibly minor bumps to `nunomaduro/collision`, `laravel/pail`, `laravel/sail` if their version
  constraints don't already satisfy Laravel 13.
* Follow the official [Laravel 13 upgrade guide](https://laravel.com/docs/13.x/upgrade) for any
  breaking changes in config files (`config/*.php`), the `bootstrap/app.php` structure (already
  on the Laravel 11+ slim bootstrap style going by this repo's layout — verify), and deprecated
  helper usage.

## Third-party package compatibility check (do this before starting)

Run `composer why-not laravel/framework:13.20.0` against the current lock file first — it
surfaces which currently-installed package (if any) pins a constraint that blocks the bump,
without changing anything. Packages to check explicitly, since they're this app's non-Laravel
dependencies:

* `ezyang/htmlpurifier` (`^4.19`) — framework-agnostic, low risk.
* `rampmaster/phpepub` (`^1.1`) — used by the epub export feature (`epub_export_v1` /
  `alias_references_v1` shipped specs); check its own `composer.json` doesn't cap PHP below 8.3.
* `secondnetwork/blade-tabler-icons` (`^3.44`) — Blade component package; check for a Laravel 13
  compatible release, since it typically follows Laravel's minor line closely.
* Dev-only: `laravel/breeze`, `laravel/sail`, `laravel/pail`, `laravel/pint`,
  `nunomaduro/collision` — only bump if Composer's resolution forces it (non-goal per the source
  spec otherwise).

## Config / bootstrap files worth a diff against a fresh `laravel new` on 13.x

Laravel major bumps occasionally touch `config/*.php` defaults (e.g. new keys, renamed drivers).
Diff at minimum: `config/app.php`, `config/logging.php`, `config/session.php`,
`config/filesystems.php` (this app uses local disk for uploads/exports) against the upgrade
guide's changelog — don't blindly overwrite, since this app's configs are customized.

## Documentation to update after both bumps land

* `documentation/architecture.md` — if it states the PHP/Laravel version anywhere.
* `README.md` / `melusine.md` — same check.
* `CHANGELOG.md` — add an `Unreleased` → `Changed` entry per the project's changelog convention.
