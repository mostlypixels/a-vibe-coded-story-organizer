---
status: draft
---

# Upgrade to Laravel 13

The project currently runs Laravel 12.62.0 on PHP 8.2.18 (WAMP). Laravel 13 (currently
v13.20.0) is out and requires PHP `^8.3`, so this is a two-part upgrade: bump the local PHP
version first, then bump the framework.

## Goals

* Move WAMP's active PHP from 8.2 to a version that satisfies both Laravel 12's `^8.2` and
  Laravel 13's `^8.3` constraints — PHP 8.3, 8.4, or 8.5. A compatible build is already
  available locally in the WAMP PHP install directory; switching WAMP's active version to it
  requires no new download.
* Bump `composer.json`'s `"php"` constraint and `laravel/framework` to `^13.0`.
* Keep the app fully working after both bumps — no behavior change is intended, this is a
  maintenance upgrade.

## Non-goals

* No new features. This is purely a dependency/runtime upgrade.
* Not upgrading `laravel/breeze`, `laravel/sail`, or other dev-only tooling beyond what's
  required to stay compatible with Laravel 13, unless the upgrade forces it.

## Rough approach

1. **PHP first.** Switch WAMP's active PHP module to the 8.3+ build already installed while
   still on Laravel 12, and run `composer test` to isolate any PHP-only
   deprecations/breakage from the framework bump.
2. **Then Laravel.** Run `composer require laravel/framework:^13.0` (and let Composer resolve
   any transitively-required package bumps — Symfony 7.4/8.0, etc.) and follow the official
   [Laravel 13 upgrade guide](https://laravel.com/docs/13.x/upgrade).
3. **Check third-party packages** already in `composer.json` for Laravel 13 / PHP 8.3+
   compatibility before starting: `ezyang/htmlpurifier`, `rampmaster/phpepub`,
   `secondnetwork/blade-tabler-icons`. A `composer why-not laravel/framework:13.20.0` dry run
   against the current lockfile is a good first check.
4. Run the full test suite (`composer test`) after each of the two bumps, not just at the end,
   so any failure is attributable to one change or the other.
