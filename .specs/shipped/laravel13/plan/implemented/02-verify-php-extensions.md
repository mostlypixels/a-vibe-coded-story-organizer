# 02 — Verify PHP 8.5 extensions

## Scope

Confirm the PHP 8.5.7 build WAMP now serves has every extension the app needs, and fix
`php.ini` (the 8.5 build's own ini, not the old 8.2 one) if anything is missing. Still on
Laravel 12 — no `composer.json` change here.

## Depends on

Task 01 (WAMP must already be serving 8.5.7).

## Key decisions already made

* Required extensions per `composer.json`: `ext-intl`, `ext-zip`. Also confirm whatever WAMP
  enables by default and the app implicitly relies on: `pdo_sqlite` (tests run on in-memory
  SQLite per `phpunit.xml`), `mbstring`, `openssl`, `curl`, `fileinfo`, and `gd`/`zip` if used by
  the epub export path (`rampmaster/phpepub`).
* Check via `php -m` against the 8.5.7 binary specifically (not a stale PATH entry) and compare
  against the currently-enabled list in the old 8.2.18 `php.ini`, so nothing that was previously
  on silently drops off.
* If an extension is missing, enable it in `C:\wamp64\bin\php\php8.5.7\php.ini` (uncomment the
  `extension=` line) rather than working around its absence in code.

## Docs to consult

* `expanded/architecture.md` — extension list.

## Tests

No new PHPUnit test. Verification:

1. `php -m` (confirm it's the 8.5.7 binary — `php -v` first) lists `intl`, `zip`, `pdo_sqlite`,
   `mbstring`, `openssl`, `curl`, `fileinfo`.
2. `composer test` green (repeat of task 01's check — cheap, and confirms nothing regressed from
   any `php.ini` edit made in this task).
3. Manually export an epub from the running app (Apache-served, PHP 8.5.7) to exercise
   `rampmaster/phpepub`'s actual extension usage, not just `composer test`'s coverage.
