# Overview — Upgrade to Laravel 13

## Problem statement

The app runs Laravel 12.62.0 on PHP 8.2.18 under WAMP. Laravel 13 (currently v13.20.0) requires
PHP `^8.3`, so getting to Laravel 13 is really two upgrades stacked: the PHP runtime WAMP serves
the app with, then the framework and its dependents in `composer.json`. Neither bump is expected
to change app behavior — this is maintenance, not a feature.

## Goals

* WAMP's active PHP module becomes 8.3, 8.4, or 8.5 (a compatible build already exists in the
  WAMP PHP install directory — no download needed), satisfying both Laravel 12's `^8.2` and
  Laravel 13's `^8.3` constraint.
* `composer.json`'s `"php"` constraint and `laravel/framework` move to `^13.0`.
* `composer test` passes after each bump, and the app behaves identically to a user.

## Non-goals

* No new features or behavior changes.
* No upgrade of `laravel/breeze`, `laravel/sail`, or other dev-only tooling beyond what Laravel
  13 compatibility forces.
* No CI changes — the repo has no `.github/workflows/`, so this upgrade is entirely local/manual
  (WAMP tray + `composer.json` + running the suite by hand).

## User stories / acceptance criteria

This is an infrastructure upgrade with no end-user-facing story. Acceptance is:

1. WAMP is serving the app on PHP 8.3+ (verified via `php -v` and the WAMP tray icon), with
   Laravel 12 still installed at that point, and `composer test` is green.
2. `composer.json` requires `"php": "^8.3"` and `"laravel/framework": "^13.0"`; `composer.lock`
   reflects the resolved Laravel 13 dependency tree; `composer test` is green.
3. The app loads and the core flows (login, story tree navigation, editing a scene) work when
   manually exercised — see `testing.md`.
4. `documentation/architecture.md` and any other doc that names the Laravel/PHP version is
   updated to match.

## Rough sequencing (unchanged from the source spec)

1. PHP first: switch WAMP's active PHP module to 8.3+ while still on Laravel 12; run
   `composer test` to isolate PHP-only breakage from the framework bump.
2. Laravel second: `composer require laravel/framework:^13.0`, let Composer resolve transitive
   bumps (Symfony 7.4/8.0, etc.), follow the official Laravel 13 upgrade guide.
3. Check third-party packages for compatibility before starting (see `architecture.md`).
4. Run `composer test` after each of the two bumps, not just at the end.
