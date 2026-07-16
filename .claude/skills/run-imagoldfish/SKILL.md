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
Node/npm must already be on PATH — this repo assumes a local dev stack is
already set up (no apt-get step here; this is not a container). Verify with
`php --version` / `node --version` (PHP 8.5 / Node v20+ expected).

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

## Run (agent path)

Start the server in the background and poll it, then pipe a script to the
driver:

```bash
(php artisan serve --port=8000 > "$TMPDIR/artisan-serve.log" 2>&1 &)  # or your scratchpad dir
timeout 30 bash -c 'until curl -sf http://localhost:8000 -o /dev/null; do sleep 1; done'
```

The app requires a logged-in session for almost everything. Create a
throwaway user once via tinker (delete it again when you're done — this
writes to the real dev database, there is no test-mode toggle):

```bash
php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'agentcheck@example.com', 'password' => bcrypt('password')]); echo 'created';"
```

Then drive the app:

```bash
cd .claude/skills/run-imagoldfish
node driver.mjs --session mycheck <<'EOF'
nav http://localhost:8000/login
wait-for input[name=email]
fill input[name=email] agentcheck@example.com
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

When done: delete the throwaway user (`User::where('email', '...')->delete()`
via tinker) and stop the server —

```bash
# PowerShell — find the exact PID, don't broadly pkill php (other php
# processes, e.g. PhpStorm's own interpreter, may be running):
Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object { $_.CommandLine -match 'artisan serve' } | Select-Object ProcessId
Stop-Process -Id <that-id> -Force
```

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

## Test

```bash
composer test
```
The full suite must be green. This uses an in-memory SQLite DB — it does **not** prove the dev-server-served app works;
see the migration gotcha above.

```bash
./vendor/bin/pint --test
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
  interpreter, may be running). Find the exact PID via
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
