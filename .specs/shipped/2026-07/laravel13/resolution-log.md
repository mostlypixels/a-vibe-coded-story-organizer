# Laravel 13 upgrade — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and issues →
resolutions found while implementing and verifying this feature. The `plan-implementer` agent
appends here per task; `ship-plan` consolidates it. Read it before extending the feature.

## Feedback & decisions

* Target PHP is **8.5**, not the source spec's minimum-viable 8.3 — the build is already
  installed (`C:\wamp64\bin\php\php8.5.7`) and the CLI PATH already resolves to it; only Apache's
  `httpd.conf` still pointed at 8.2.18. Decided during the `plan-tasks` grill (2026-07-15).
* The WAMP PHP switch (editing `httpd.conf`'s `LoadModule` line and restarting the Apache
  service) is done **directly by the plan-implementer agent**, not left as a manual step for the
  user — explicit decision during the grill (2026-07-15), overriding the default caution around
  editing system files outside the repo.

* **Task 04 — manual smoke test passed on the fully-upgraded stack (PHP 8.5.7 + Laravel 13.20.0),
  driven through the *running app* (not a CLI bootstrap this time).** No file changed (verification
  task), so nothing regressed to task 03. The app was served via `php artisan serve` on the 8.5.7
  CLI binary (Apache still elevation-blocked per task 01); a clean `npm run build` preceded it, no
  `public/hot`, and the served HTML's asset URLs point at `/build/assets/…` — the built Vite
  manifest, not a `:5173` dev server. `X-Powered-By: PHP/8.5.7` on every response. All five checklist
  steps confirmed by real HTTP + a headless browser (Playwright driver), not the test suite:
  1. **Login** — POST `/login` → 302, session established.
  2. **Story overview** (`/projects/{id}/story`) — the nested act → chapter → scene eager-load tree
     rendered fully (Act 1 → Chapter 1 → "Opening Scene" + contents), Tailwind styles loaded from
     the build, **zero console errors** — no N+1/eager-load regression.
  3. **Edit + save a scene** (`PATCH /scenes/{id}`) — renamed the scene, save redirected to the
     scenes index showing the new name; exercised the Form Request + `ScenePolicy` + `ValidMarkdown`
     path with no console errors.
  4. **Epub export** (`POST /admin/data/export/epub`) — HTTP 200, `Content-Type:
     application/epub+zip`, produced a valid 4517-byte EPUB (11 entries: `mimetype`,
     `META-INF/container.xml`, `OEBPS/book.opf`/`book.ncx`, act/chapter/title/toc XHTML) — the full
     `rampmaster/phpepub` path through the live HTTP stack, including `EpubExportRequest` auth.
  5. **`/robots.txt`** — HTTP 200, rendered `User-agent: * / Disallow: /` (the default-hidden
     `CrawlerSetting` singleton) from the dynamic `RobotsTxtController` route.
  Cleanup: the throwaway `agentcheck@example.com` user and its project tree were created for the run
  and deleted afterward (dev sqlite left with its original 4 real users); `artisan serve` stopped.

* **Task 05 — docs updated to Laravel 13.20.0 / PHP 8.5.7.** `documentation/architecture.md:3`
  now reads "This is a Laravel 13 app (PHP 8.5, …)" — the runtime version was added even though
  the original sentence named none, since the stack is now worth stating. A `CHANGELOG.md`
  `## [Unreleased]` → `### Changed` entry records the bump (framework 13.20.0, PHP 8.5.7, forced
  `laravel/tinker ^3.0`, `ext-imap` intentionally dropped). Re-grep of `documentation/`,
  `README.md`, `melusine.md` for `Laravel 12` / `8.2` returns nothing — no stale reference left.
  README.md and melusine.md never named a version, so neither changed.
* **Task 05 — out of scope but noted:** `.claude/skills/run-imagoldfish/SKILL.md` still says
  "Laravel 12 + … PHP 8.2 (wamp64)" / "8.2.18 (wamp64) used when this skill was verified". That is
  a skill-tooling file, not under `documentation/`/`README.md`/`melusine.md` (this task's scope),
  and its 8.2.18 line is a record of when the skill was last verified rather than a current-stack
  claim. Left unchanged; flagging it so a future pass can refresh the skill if desired.

## Deviations from the spec/plan

* **Task 03 — `laravel/tinker` constraint was bumped manually to `^3.0`, contrary to the plan's
  "no manual tinker bump, Composer resolves it automatically".** Root cause: the root
  `composer.json` pinned `"laravel/tinker": "^2.10.1"`, and `composer update laravel/framework -W`
  does *not* relax a root constraint on a package it isn't told to update — it failed with a
  resolution conflict (`tinker v2.11.1 requires illuminate/support ^…|^12.0`, cannot coexist with
  framework 13). The planner's `why-not` reading was optimistic. The fix is exactly what the
  official Laravel 13 upgrade guide directs (`laravel/tinker` → `^3.0`); ran
  `composer update laravel/framework laravel/tinker -W`, which resolved cleanly to framework
  v13.20.0 + tinker v3.0.2. No other root constraint needed relaxing.
* **Task 03 — no `config/*.php` or `bootstrap/app.php` change was required.** `bootstrap/app.php`
  is already the slim Laravel 11+ style with empty middleware/exception closures. `config/cache.php`
  already uses the L13 hyphenated prefix (`'-cache-'`), and both cache prefix and session cookie
  are set explicitly (not via framework fallback), so the L13 cache/cookie-prefix hyphenation change
  is inert here. None of the guide's renamed symbols are referenced in app code
  (`VerifyCsrfToken`/`PreventRequestForgery`, `exceptionOccurred`, `QueueBusy`, `JobAttempted`,
  `serializable_classes`, `pagination::default`). The one hit — `Illuminate\Support\Js::from` in
  `resources/views/components/string-list.blade.php` — is affected only by the Very-Low-impact
  `JSON_UNESCAPED_UNICODE` default, which renders the same characters and is safe. No proactive bump
  of `laravel/breeze`/`sail`/`pail`/`pint`/`nunomaduro/collision`/`phpunit` was needed and
  Composer's resolution did not force any (Symfony components moved 7.4 → 8.1 transitively).

* **Task 02 — no `php.ini` change was required.** The 8.5.7 build already has every extension the
  app needs, so nothing was uncommented. `php -m` on `C:\wamp64\bin\php\php8.5.7\php.exe` lists
  `intl`, `zip`, `pdo_sqlite`, `mbstring`, `openssl`, `curl`, `fileinfo`, and `gd` — plus `dom`/`zip`
  for `rampmaster/phpepub`. The only extension present in the old 8.2.18 build but absent from 8.5.7
  is `imap` (removed from PHP core in 8.4, now PECL-only); it is not used anywhere in the app
  (`grep imap` over `app/` is empty) so it was intentionally left off, not restored.
* **Task 02 — the "manual epub export from the running app" (verification step 3) was exercised via
  a bootstrapped CLI script + DB rollback, not through Apache.** Apache still cannot be started in
  this session (elevation-blocked, per task 01), and the app is normally served via `php artisan
  serve` on the same 8.5.7 CLI binary anyway. The export was driven by loading the Laravel kernel
  under `php8.5.7\php.exe`, creating a throwaway project/act/chapter/scene inside a DB transaction,
  calling `EpubExporter::export()`, asserting the produced `.epub`, then rolling the transaction
  back (dev sqlite untouched).

* **Task 01 — Apache service restart done by running `httpd.exe` directly, not via the service
  manager.** The plan authorized restarting `wampapache64` via `net stop/start`. In practice all
  WAMP services were already **stopped** (WAMP not running) and `Start-Service` / `net start
  wampapache64` both failed with **System error 5 / Access denied** — the Windows Service Control
  Manager requires elevation, which the non-interactive session does not have. Rather than force
  elevation, verification was done by launching `httpd.exe` directly as the current user (see
  Issues below), then stopping it to leave WAMP in its original stopped state. The `httpd.conf`
  edit is what persists; the user's normal WAMP-tray start will now load PHP 8.5.7.

## Issues → resolutions

* **Task 03 — `vendor/bin/pint --test` reports two failures, both pre-existing and unrelated to the
  bump.** `database/seeders/MelusineSeederFr.php` and `MelusineSeederIt.php` trip the `single_quote`
  fixer. This task changed no PHP source (`git status` shows only `composer.json` / `composer.lock`),
  so the failures predate it, and the French/Italian Melusine seeder variants are left untouched by
  standing instruction. Not a Laravel 13 regression; left as-is.

* **Task 03 — `composer test` stayed green: 539 passed / 2013 assertions**, identical to the
  task-01 baseline on PHP 8.5.7 + Laravel 12, confirming no behavior change from the framework bump.
  Runtime surface was additionally verified via `php artisan serve`: the guest welcome page, `/login`,
  and `/robots.txt` all returned HTTP 200 under Laravel 13.20.0, and the served HTML's asset URLs
  point at `/build/assets/…` (the built Vite manifest — no `public/hot`, no `:5173` dev-server
  origin), after a clean `npm run build`.

* **Task 02 — piping the smoke-test PHP into `artisan tinker` failed with a PHP parse error.** Root
  cause: tinker's PsySH REPL evaluates its stdin as already-open PHP, so a leading `<?php` tag (and
  the multi-line `use`/`try`/`finally` script) is a syntax error in that context. Resolution — ran
  the export as a standalone bootstrapped script under `php8.5.7\php.exe` instead: `require`
  `vendor/autoload.php`, then `bootstrap/app.php`, then `$app->make(Kernel::class)->bootstrap()`.
  (First attempt omitted the autoloader `require` and hit `Class "Illuminate\Foundation\Application"
  not found` — adding the `vendor/autoload.php` require fixed it.) The export then produced a valid
  4565-byte, 11-entry `.epub` (mimetype `application/epub+zip`, `OEBPS/book.opf` present) with
  `validatePackage()`'s `relaxNGValidate` passing — confirming `ext-dom` and `ext-zip` work on 8.5.7.

* **Task 01 — could not confirm "serves a page without 500" through the running Apache service
  (service start blocked by lack of elevation).** Root cause: SCM access needs an elevated
  process; this session is non-interactive/unelevated. Resolution — proved the PHP-8.5.7 module
  switch three ways that don't need the service: (1) `httpd.exe -t` returned **Syntax OK**, and the
  config test loads the `LoadModule php_module .../php8.5.7/php8apache2_4.dll` directive, so the
  8.5.7 Apache-bridge DLL loads cleanly; (2) ran `httpd.exe` directly in the background and fetched
  a PHP endpoint, which returned **HTTP 200** with body `PHPVER:8.5.7`, proving Apache both loads
  and *executes* PHP 8.5.7; (3) `composer test` is green (539 passed / 2013 assertions) on the
  8.5.7 CLI. Note: the imagoldfish project is **not** under Apache's DocumentRoot
  (`C:/wamp64/www`) and has no project vhost — it is normally served via `php artisan serve`
  (`APP_URL=http://localhost:8000`) on the same 8.5.7 CLI binary; the Apache check confirms the
  module switch itself. The WAMP homepage `index.php` timed out during the check, but that is the
  homepage probing the (stopped) MySQL/MariaDB services, not a PHP fault — the plain PHP endpoint
  returned 200.
