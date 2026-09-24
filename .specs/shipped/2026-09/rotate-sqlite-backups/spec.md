---
status: shipped
expanded: 2026-09-08
shipped: 2026-09-24
---

# Rotate SQLite backups

The whole app is one file. `database/database.sqlite` holds every project, chapter, scene
and revision, and nothing copies it anywhere. A bad migration, a corrupt write, or a
mistaken `migrate:fresh` takes the lot, and the recovery story today is "there isn't one".

That is survivable while the only data is the Melusine seed. It stops being survivable in
November, when a month of real writing lives in that file.

## Goals

- One command that writes a dated snapshot of the database and deletes all but the newest
  few.
- Runs daily on the schedule, beside `exports:purge` and the revision prune in
  `routes/console.php`.
- Safe to run while the app is running. No stopping the server, no second writer.
- Runnable by hand before anything risky, into a directory of the caller's choosing.
- A snapshot is identified by a name and the moment it was taken, so a hand-made one before
  a migration is tellable from last night's scheduled one.
- A snapshot restores by copying one file back.

## Non-goals

- No off-machine backup. Copying the snapshots to another disk or a cloud bucket is a
  different problem with different failure modes.
- No restore command. Restoring is `cp` and stopping the app, and a command that overwrites
  the live database is worth more care than this feature has.
- No per-project export. `archive-and-delete` and the existing project export own that.
- No support for other database engines. See `multiple-database-engines` (shelved).
- Not a replacement for revisions. Revisions protect prose within the app; this protects
  the file the app lives in.

## Approach

- `VACUUM INTO '<path>'` rather than a file copy. It is one statement, it produces a
  consistent snapshot while other connections are open, and it never writes to the live
  database. A `cp` of a database being written to can yield a torn file — a backup that
  looks fine until the day it is needed.
- The command takes the destination directory as an option, defaulting to a config value
  the way `exports:purge` defaults `--hours` to `exports.purge_after_hours`.
- A snapshot's filename carries a name and a datetime. The name defaults to something plain
  and is overridable, so `--name=before-migrate` gives a file the writer can recognise a
  week later. The datetime makes it sortable and unique.
- Retention by count, not age: keep the newest N, delete the rest. A `--keep` option
  overrides the config default, as `exports:purge` does with `--hours`.
- Follow `App\Console\Commands\PurgeExports` for the shape: a documented signature, a
  `--dry-run`, a readable summary line, and an early return when there is nothing to do.
- Abort with a clear message when the connection is not SQLite, rather than producing
  nothing quietly.

## Open ends

- How many to keep, and whether daily is enough during a month of daily writing.
- Whether retention counts per name or across the whole directory. Keeping the newest N
  overall would let a run of scheduled snapshots quietly delete a named one taken before a
  migration — which is the snapshot most worth keeping.
- Whether it should hook anything risky automatically — `migrate`, `migrate:fresh`, a
  project import — or stay something the writer and the schedule run.
- Whether the snapshot directory belongs in `storage/` at all, given `storage/` is the
  thing a careless `rm -rf` also takes.
- Whether to turn on WAL (`journal_mode`) so a snapshot never makes a write wait. Its own
  change, but this feature is the reason to consider it.
- Whether a snapshot that fails should be loud (fail the schedule) or quiet.
