# Open questions

1. **Does retention count per name, or across the directory?**
   Recommend per name. Counting across it lets seven nightly snapshots quietly delete the
   `before-migrate` one taken an hour ago — the snapshot most worth keeping. Per name costs
   one `groupBy` and removes the failure mode.

2. **How many to keep, and is daily enough in November?**
   Recommend 7 daily as the default, and a second scheduled run hourly during a writing
   month, added by hand when it starts. The database is under a megabyte; a week of hourly
   snapshots is still trivial. The real question is whether losing an hour of writing is
   acceptable — the answer for NaNoWriMo is probably no.

3. **Should anything risky trigger a snapshot automatically?**
   Recommend no, for now. A hook on `migrate` is the tempting one, but a command that
   silently writes files during a deploy is a surprise, and the writer running
   `db:backup --name=before-migrate` by hand is one line she can see. Revisit once the
   command has been used.

4. **Where do snapshots live?**
   Recommend `storage/app/backups`, matching `exports.temp_path`. It has one flaw worth
   naming: `storage/` is also what a careless clean-up removes, so the backup and the thing
   it protects can die together. A path outside the repo is safer and less discoverable —
   the config default is the lever either way.

5. **Two runs in the same second — refuse, or disambiguate?**
   Recommend refuse, with a message. Silently appending a counter hides that something ran
   twice, and the second run has nothing new to snapshot.

6. **Should the command turn on WAL?**
   No — but this feature is the reason to consider it. `config/database.php` has
   `'journal_mode' => null`. In rollback-journal mode a snapshot briefly makes a concurrent
   write wait; in WAL it does not. At this database's size the wait is milliseconds. Its own
   change, its own spec.

7. **Loud or quiet when a scheduled snapshot fails?**
   Recommend loud: non-zero exit, and no `->withoutOverlapping()` swallowing. A backup that
   silently stopped working is worse than none, because it is believed.

8. **Is `db:backup` the right name?**
   Recommend yes over `db:rotate` — the rotation is a consequence, the snapshot is the
   point. It also reads correctly in `db:backup --name=before-migrate`.

## Answers (owner, 2026-09-24)

- **Retention:** per name. Other names are never deleted.
- **Schedule and count:** every `backup.every_hours` hours (default 1, range 1–24), keep `backup.keep` (default 48). Both have env overrides.
- **Automatic hooks:** none. The writer runs `db:backup --name=before-migrate` by hand.
- **Location:** `storage/app/backups` by default, git-ignored. Off-disk copies are the writer's job.
- **Same second:** refuse with a message and a non-zero exit.
- **WAL:** no change.
- **Failure:** loud. Non-zero exit; errors are not swallowed.
- **Name:** `db:backup`; the default snapshot name is `scheduled`. Config file is `config/backup.php`.
- **Tests:** a temporary file database per test, not `RefreshDatabase`.
