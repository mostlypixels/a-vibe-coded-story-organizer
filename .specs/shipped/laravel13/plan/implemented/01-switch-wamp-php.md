# 01 — Switch WAMP's active PHP to 8.5

## Scope

Switch Apache's active PHP module from 8.2.18 to 8.5.7, while the app stays on Laravel 12.
Confirm the app still works under the new PHP version before any `composer.json` change (that's
task 03). Does **not** touch `composer.json` or any app code.

## Depends on

Nothing — this is the first task.

## Key decisions already made

* Target is PHP **8.5**, build already at `C:\wamp64\bin\php\php8.5.7` (confirmed present).
* Apache currently loads PHP via
  `C:\wamp64\bin\apache\apache2.4.59\conf\httpd.conf:201`:
  ```
  LoadModule php_module "${INSTALL_DIR}/bin/php/php8.2.18/php8apache2_4.dll"
  ```
  Change the version segment to `php8.5.7` (confirm the `.dll` filename inside that folder
  matches `php8apache2_4.dll` before editing — WAMP's PHP-Apache bridge DLL name is consistent
  across versions, but verify rather than assume).
* Restart the Apache service after the edit — WAMP's tray tool (`wampmanager`) normally does
  this when you switch versions through its menu; from a shell, `net stop wampapache64` then
  `net start wampapache64` is the equivalent (service name may differ — check
  `Get-Service *wamp*` first). Editing `httpd.conf` without restarting Apache has no effect.
* Do not touch `LoadModule` lines for anything other than PHP, and don't edit any other
  `httpd*.conf` file.

## Docs to consult

* `expanded/architecture.md` (Runtime / WAMP section) for the extension list to sanity-check
  after the switch (full check is task 02).

## Verification (this task's "test")

There's no PHPUnit test for a runtime switch. Verify by:

1. `Get-Service *wamp*` / restart, then confirm httpd.conf now references `php8.5.7`.
2. Hit the app in a browser (or `php artisan serve` isn't the relevant path here — this is the
   Apache-served path) and confirm a page loads without a 500.
3. `composer test` from the repo root — must be green on PHP 8.5.7 + Laravel 12 before moving to
   task 02/03. If it fails, stop and diagnose here; don't proceed to the framework bump with an
   unexplained PHP-only failure (see `00-overview.md` invariant).
