# Testing

`tests/Feature/BackupDatabaseTest.php`, shaped like `PurgeExportsTest`: fixtures written
straight into the configured directory, which `Tests\TestCase` points at a per-test path.

## The transaction problem

`RefreshDatabase` wraps each test in a transaction, and `VACUUM INTO` cannot run inside one.
A probe confirms it:

```
SQLSTATE[HY000]: General error: 1 cannot VACUUM from within a transaction
```

So this class cannot use `RefreshDatabase`. Options, in order of preference:

1. `DatabaseMigrations` for this class — migrates per test, no wrapping transaction. Slower,
   but it is one small class.
2. Point the test at its own file-based SQLite database and snapshot that, leaving the
   suite's connection alone.

Decide in the grill; whichever wins, say in the test class docblock *why* it is not
`RefreshDatabase`, or someone will "fix" it back.

## Cases

| Case | Expect |
|---|---|
| A plain run | one file matching `<default name>-<datetime>.sqlite` |
| `--name=before-migrate` | the name appears in the filename |
| The snapshot's contents | opens as SQLite and holds a row written before the run |
| The live database | unchanged — size, mtime and contents |
| `--path=` a directory that does not exist | created, then written |
| `--path=` a directory that cannot be written | non-zero exit, message names the path |
| `--keep=3` with five snapshots present | the newest three survive |
| Retention with two names present | see `open-questions.md`; assert whichever rule wins |
| A foreign file in the directory | untouched by the prune |
| `--dry-run` | nothing written, nothing deleted, output says what would happen |
| A non-SQLite default connection | non-zero exit, message names the connection |
| `--name` holding a slash or a space | rejected, nothing written |
| Two runs in the same second | the first snapshot survives |

## Not worth a test

That `VACUUM INTO` produces a valid database — that is SQLite's job, and the contents
assertion above already covers the part we depend on.
