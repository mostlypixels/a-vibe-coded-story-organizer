# Architecture

## The command

`App\Console\Commands\BackupDatabase`, signature `db:backup`. Modelled on `PurgeExports`.

```
db:backup
    {--path=  : Directory to write into (default: backups.path)}
    {--name=  : Name for this snapshot (default: backups.default_name)}
    {--keep=  : How many snapshots to keep (default: backups.keep)}
    {--dry-run : Report what would happen without writing or deleting}
```

Order of work: validate → snapshot → prune. Pruning after the write means a failed snapshot
never costs an old one.

## The snapshot

`VACUUM INTO '<path>'`, issued through `DB::statement()`. Not a file copy.

- It is one statement producing a consistent database, with other connections open.
- It never writes to the live file, so it is a reader, not the second writer
  `CLAUDE.md` warns about.
- A `cp` of a database being written to can yield a torn file — a backup that looks fine
  until the day it is needed.

> [!WARNING]
> `VACUUM` cannot run inside a transaction. This is not theoretical: a probe under
> `RefreshDatabase` fails with *cannot VACUUM from within a transaction*. It shapes the
> tests (see `testing.md`) and means the command must never be called from inside
> `DB::transaction()`. Say so in the docblock.

The destination path must be quoted for SQL. Build it with the PDO quoting rather than
string concatenation — a directory name is user input from `--path`.

## Filenames

`<name>-<Y-m-d_His>.sqlite`, e.g. `nightly-2026-09-08_031500.sqlite`,
`before-migrate-2026-09-08_164211.sqlite`.

- The datetime sorts lexicographically in the same order as chronologically, so pruning can
  sort by name and never stat a file.
- Two runs in the same second: the second must not overwrite the first. Refuse, or append a
  counter — `open-questions.md`.
- `--name` is part of a filename, so it needs the same discipline as a slug: reject anything
  but `[A-Za-z0-9_-]`, rather than sanitising quietly. A rejected name is better than a
  snapshot the writer cannot find.

## Retention

Keep the newest N, delete the rest, counting **per name** — see `open-questions.md` for why
counting across the whole directory is the wrong default.

Only files matching the snapshot pattern are candidates. Anything else in that directory
belongs to something else, exactly as `PurgeExports` skips directories.

## Config

`config/backups.php`, in the style of `config/exports.php` — each value with a comment
saying *why*, not what.

```php
'path'         => env('BACKUPS_PATH', storage_path('app/backups')),
'keep'         => (int) env('BACKUPS_KEEP', 7),
'default_name' => env('BACKUPS_DEFAULT_NAME', 'nightly'),
```

The path is a config value for the same reason `exports.temp_path` is: the suite runs under
paratest, and two processes sharing a directory would prune each other's fixtures.

## Schedule

One line in `routes/console.php` beside the existing two, with a comment saying why it
exists. Daily.

## Guards

- Abort when `DB::connection()->getDriverName()` is not `sqlite`, naming the connection.
  A silent no-op on MySQL is how someone discovers there are no backups at restore time.
- Create the destination directory when missing; fail loudly when it cannot be created or
  written.
- Non-zero exit on failure, so a failed scheduled run is visible rather than assumed.

## Untouched

Migrations, models, policies, and anything user-facing. This feature adds one command, one
config file, and one schedule line.
