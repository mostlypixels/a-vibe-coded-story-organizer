# Overview

## Problem

The app is one file. `database/database.sqlite` holds every project, chapter, scene and
revision, and nothing copies it anywhere. There is no recovery from a bad migration, a
corrupt write, or a mistaken `migrate:fresh`.

Revisions protect prose *inside* the app. Nothing protects the file the app lives in.

The deadline is what changes the calculus: a month of daily writing lands in that file in
November.

## What the code already gives us

- `App\Console\Commands\PurgeExports` is the shape to copy: documented signature, a
  `--dry-run`, an option that falls back to config, an early return when there is nothing
  to do, and one readable summary line.
- `routes/console.php` already schedules two daily sweeps, with a comment each saying why.
- `config/exports.php` shows the config style, including *why* the path is a config value:
  the suite runs under paratest, and processes sharing one directory would delete each
  other's files. The same reasoning applies here.
- `tests/Feature/PurgeExportsTest.php` is the test shape — fixtures written straight into
  the configured directory.

## Goals

- `php artisan db:backup` writes a consistent snapshot and prunes older ones.
- Daily on the schedule; runnable by hand before anything risky.
- The destination directory is an option with a config default.
- A snapshot's filename carries a name and a datetime, so a hand-made one is tellable from
  a scheduled one a week later.
- Safe while the app runs: no second writer, no stopping the server.

## Non-goals

Off-machine copies, a restore command, per-project export, other database engines, and any
change to revisions. All are named in `spec.md` with their reasons.

## Acceptance criteria

- A run produces one file whose name holds the given (or default) name and the moment.
- The snapshot is a valid SQLite database holding the same rows as the source.
- The live database file is unchanged — same size, same mtime, same contents.
- A second run with the same name in the same second does not overwrite the first.
- With `--keep=3` and five existing snapshots, three remain and they are the newest three.
- `--dry-run` writes nothing and deletes nothing, and says what it would do.
- A non-SQLite default connection aborts with a message naming the connection.
- The command fails loudly, with a non-zero exit, when the destination is not writable.
