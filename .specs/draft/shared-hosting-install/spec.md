---
status: draft
---

# Shared Hosting Install

## Problem

On shared hosting (cPanel, no SSH), the app cannot be installed or updated. A git clone has no `vendor/` or `public/build/`. `composer`, `npm` and `artisan migrate` cannot run. The user must write `.env` by hand. An old install stays old because pending migrations cannot run.

## Goals

- **Release zip:** CI builds a zip that includes `vendor/` (no dev packages) and `public/build/`. Upload, unzip, done.
- **Update database page:** an admin page lists pending migrations and runs them. It takes a `db:backup --name=before-migrate` snapshot first.
- **First-run installer:** with no `.env`, every request goes to an install page. It:
    - checks the PHP version, the extensions and the writable folders;
    - writes `.env` with a new `APP_KEY` and the app URL;
    - creates the SQLite file and runs the migrations;
    - creates the first account.
- **Lock:** after install, the installer is gone (404). It must never run a second time.

## Non-goals

- MySQL or Postgres setup. SQLite only, as now.
- Auto-update that downloads new releases.
- A UI to edit `.env` after install.
- Rollback of migrations from the browser. Restore from the snapshot.
- Multi-user roles. Solo use comes first.

## Rough approach

- Release zip: a GitHub Actions workflow next to `.github/workflows/tests.yml`, on a tag.
- Update page: under the `can:access-admin` route group in `routes/web.php`, beside the other admin pages.
- Installer: a route group outside the session and cookie middleware, because those need `APP_KEY`.
- Ship in this order: release zip, update page, installer. Each one is useful alone.

## Open ends

- `access-admin` lets any logged-in user in. Is that enough for running migrations?
- How the installer lock works: `.env` exists, a marker file, or both.
- What happens when `.env` is not writable on the host.
- A cron job for the scheduler on shared hosting (scheduled backups do not run without one).
