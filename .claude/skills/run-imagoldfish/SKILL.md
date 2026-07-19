---
name: run-imagoldfish
description: Build, run, and drive the imagoldfish Laravel app in a real browser — start the dev server, log in, click through pages, screenshot the result, upload files. Use when asked to run the app, start the dev server, take a screenshot of a page, verify a UI change actually works, or drive an end-to-end flow (login, forms, tab switching, file upload) rather than just running the test suite.
---

This is a Laravel 13 + Blade + Alpine.js + Tailwind app (PHP backend, no SPA
framework). It is driven with `php artisan serve` (no separate frontend dev
server needed once assets are built) plus a small Playwright-based headless
browser driver at `.claude/skills/run-imagoldfish/driver.mjs` — pipe it a
line-oriented script the same way you'd use `chromium-cli`.

All paths below are relative to the repo root, except the driver itself.

## Prerequisites

Windows/PowerShell environment observed in this repo. PHP 8.5, Composer, and
Node/npm must already be on PATH on the machine this skill runs from — verify with
`php --version` / `node --version` (PHP 8.5 / Node v20+ expected).

The project also has a Docker setup (`documentation/docker.md`) for humans who don't
want a local PHP/Node install, but this skill's driver (`serve-app.sh` / `driver.mjs`)
talks to a locally-run `php artisan serve`, not a container — the agent sandbox this
skill runs in is the host machine itself, not inside Docker.

## Setup

One-time, after clone (or whenever `composer.json`/`package.json` change):

```bash
composer install
npm install
cp .env.example .env   # if .env doesn't exist yet
php artisan key:generate
```

**The dev SQLite database must have migrations applied** — this is easy to
forget since `composer test` uses an in-memory DB that always starts fresh,
so a stale dev `database/database.sqlite` can pass the whole test suite while
the actual running app 500s on a page that touches a newer table:

```bash
php artisan migrate
```

(`scripts/serve-app.sh` refuses to start the server while migrations are
pending, so this can't be silently forgotten on the agent path.)

The driver's own dependencies (Playwright) live in **this skill directory**,
separate from the app's `package.json` — install them once:

```bash
cd .claude/skills/run-imagoldfish
npm install
npx playwright install chromium
cd ../../..
```

## Build

```bash
npm run build
```

Confirm `public/hot` does **not** exist afterward — if it does, `@vite` will
try to reach a dev server instead of serving the build, and every page will
fail to load its assets:

```bash
ls public/hot 2>/dev/null && echo "STALE — @vite will 404" || echo "ok"
```

(`scripts/serve-app.sh` enforces this too — it refuses to start if
`public/hot` exists or `public/build` is missing.)

## Run (agent path)

Start the server with the helper script (Git Bash) — it runs the pre-flight
checks (stale `public/hot`, missing `public/build`, pending migrations),
starts `php artisan serve` in the background, records the PID in
`scripts/.serve-app.pid`, logs to `storage/logs/artisan-serve.log`, and polls
until the URL answers. Idempotent — re-running while the server is up is a
no-op:

```bash
bash scripts/serve-app.sh            # default port 8000
bash scripts/serve-app.sh --port 8123
```

The app requires a logged-in session for almost everything. Use the seeded dev
user `admin@example.com` / `password` — `DatabaseSeeder` creates it (idempotently,
dev DB only) along with sample Melusine projects. If the dev database doesn't have
it yet:

```bash
php artisan db:seed
```

Do not create throwaway users via tinker — the seeded user exists precisely so
there's nothing to clean up afterwards.

Then drive the app:

```bash
cd .claude/skills/run-imagoldfish
node driver.mjs --session mycheck <<'EOF'
nav http://localhost:8000/login
wait-for input[name=email]
fill input[name=email] admin@example.com
fill input[name=password] password
click button[type=submit]
wait-for text=Dashboard
screenshot dashboard
console --errors
EOF
```

Screenshots land in `chromium_cli/sessions/<session>/screenshots/` inside
this skill directory (latest also copied to `screenshot.png`). Read the PNG
with the Read tool to actually look at it — a blank or error-page screenshot
means the flow didn't work, even if every driver command printed "ok".

When done: stop the server (the seeded user stays — no cleanup needed) —

```bash
bash scripts/stop-app.sh
```

It kills exactly the PID recorded by `serve-app.sh` (no process-name hunting)
and removes the PID file; it's idempotent if the server is already gone.

| driver command | what it does |
|---|---|
| `nav <url>` | navigate |
| `wait-for text=<t>` / `wait-for <css>` | wait for visible text or element |
| `click <css>` | click |
| `fill <css> <value>` | fill an input |
| `set-input-file <css> <path>` | attach a file to a `<input type=file>` |
| `press <key>` | keyboard press |
| `screenshot [name]` | full-page screenshot |
| `screenshot-element <css> [name]` | crop screenshot to one element |
| `text-content <css>` | print an element's text |
| `console --errors` | print any console/page errors seen so far |
| `eval <js>` | run JS in the page, print the result |

## Run (human path)

```bash
php artisan serve
```
Visit `http://localhost:8000` in a real browser. Ctrl-C to stop.

(Or `make up` for the Docker version — same URL, see `documentation/docker.md`.)

## Test

```bash
composer test
```
The full suite must be green. This uses an in-memory SQLite DB — it does **not** prove the dev-server-served app works;
see the migration gotcha above.

```bash
composer lint -- --test
```
Pint-clean except pre-existing `database/seeders/MelusineSeederFr.php` /
`MelusineSeederIt.php` (French/Italian seeder variants — leave those alone
per project convention, they're not part of your diff).

---

## Gotchas

- **Dev SQLite DB can be behind migrations even when tests pass.** `composer
  test` runs against a fresh in-memory DB every time, so a new
  migration can sit unapplied in your actual `database/database.sqlite`
  indefinitely. Symptom: `SQLSTATE[HY000]: General error: 1 no such table:
  <x>` in `storage/logs/laravel.log`, 500 in the browser, green test suite.
  Fix: `php artisan migrate`.
- **Tabs are `<button id="...">`, not `<a>` links.** e.g. the admin
  Export/Import tabs (`resources/views/admin/data/index.blade.php`) are
  Alpine `x-show`-toggled buttons with stable ids (`#tab-import` /
  `#panel-import`), not anchors. A `has-text("Import")` selector can silently
  match an unrelated sidebar link instead (e.g. "Export **& import**" in the
  left nav contains "import" as a substring) — the click "succeeds" and a
  `wait-for text=...` can even pass by matching unrelated already-visible
  text, while the panel never actually switched. Always inspect the Blade
  file for a stable `id`/`aria-controls` before guessing a text selector, and
  verify by reading the screenshot, not just by the driver printing `ok`.
- **`pkill -f` / broad `Stop-Process` by name is not allowed by the sandbox
  and isn't a good idea anyway** (other `php` processes, e.g. an IDE's own
  interpreter, may be running). `scripts/stop-app.sh` avoids the problem
  entirely by killing the exact PID that `scripts/serve-app.sh` recorded. If
  a server was started *outside* the script (no PID file), fall back to
  finding the exact PID via
  `Get-CimInstance Win32_Process -Filter "Name = 'php.exe'"` filtered on
  `CommandLine -match 'artisan serve'`, then `Stop-Process -Id <id> -Force`.
- **Uploading a real file needs a real archive**, not a zero-byte
  `fake()->create()` — this app's importer content-sniffs uploaded zips, so
  a placeholder file will (correctly) get rejected by validation before you
  ever reach the flow you're trying to screenshot. Build/export a real
  fixture first (e.g. via `StaticSiteExporter` in tinker) and
  `set-input-file` that path.
- **Don't assume the shell cwd** — start any command block that needs the
  skill directory as cwd with an explicit `cd` (or use absolute paths).

## Troubleshooting

- **Browser flow 500s with no obvious cause**: check
  `storage/logs/laravel.log`, not the `artisan serve` stdout log — Laravel's
  exception handler renders the error page but the built-in server's own log
  only shows the request line, not the stack trace.
- **`click`/`wait-for` "succeed" but the screenshot shows the wrong panel**:
  your selector matched the wrong element (see Gotchas above). Re-check with
  a stable `id` selector instead of `has-text`.
