# Technical debt

[Documentation](../README.md) › [Development](README.md) › Technical debt

Known shortcuts that the code keeps on purpose. Each entry says what bites and when to pay it off. Remove an entry in the pull request that fixes it.

## Every account is an admin

- **Where:** `Gate::define('access-admin', …)` in `AppServiceProvider` returns `true`.
- **Risk:** any signed-in user can change the global settings. The revision retention window is global, so one user can prune the revisions of every user.
- **Why it stays:** the app has one writer. A role model is multi-user work.
- **Pay off:** before a second account uses the same install.

> [!WARNING]
> The storage panel and the purges on the Revisions page already scope to the user's own projects. The retention window does not.

## MCP tools skip authorization

- **Where:** `app/Mcp/Tools/`. A tool has no request user and calls no policy. It acts as the project owner (`$project->user`).
- **Risk:** any client of the server can read and annotate every project on the install.
- **Why it stays:** the server is stdio only, started inside Docker by the one person who owns the install. There is no HTTP route.
- **Pay off:** before a second account uses the same install. Add an authenticated HTTP transport and a policy check per tool.

## Static analysis stops at level 5

- **Where:** `phpstan.neon` sets `level: 5`.
- **Risk:** Larastan does not ask for missing parameter and array types. Level 6 reports about 70 errors.
- **Why it stays:** level 6 is mostly docblock work and rarely finds a bug. Levels 7 and 8 report hundreds of possible nulls from Eloquent relations, which cost more than they find.
- **Pay off:** raise to level 6 when there is time for docblock work. Fix the code. Do not add a baseline or an inline ignore.

> [!NOTE]
> Give each relation a generic `@return`, for example `HasMany<Chapter, $this>`. Type revision code as `Model&Revisionable`. Without these, Larastan sees a plain `Model` and reports every property as undefined.
>
> Larastan treats `Collection` element types as exact. A declared `Collection<string, int>` rejects a `Collection<string, int<0, max>>`, so declare the precise type.

## Deferred major dependency upgrades

- **Where:** `composer.lock`.
- **Risk:** `guzzlehttp/guzzle` 8 and `brick/math` 1.0 are out. Laravel accepts them, but they are major versions, so they can break code.
- **Why it stays:** the security updates did not need them.
- **Pay off:** in a pull request of its own, with the full suite. Run `composer update --with-dependencies` and read each changelog.

## Comments not in Simplified Technical English

- **Where:** older docblocks, for example in `FieldAutosaver`, `RevisionSettingController` and the `Concerns` traits.
- **Risk:** long sentences slow down a junior reader. See [comments](code-style.md#comments).
- **Pay off:** rewrite a docblock when you change its code. Ask before you shorten a long comment.

## Orphan files after a crash

- **Where:** `CodexEntryDuplicator`, `CoverImageService::saveWithCover()`.
- **Risk:** a crash between the file write and the database commit leaves a file that no row points to. No user sees it. It only uses disk space.
- **Why it stays:** a database rollback cannot remove a file, so the code puts the risk on the side that loses nothing.
- **Pay off:** if disk use matters, add a sweep like `imports:purge` for the media disk.

## Slow file access in Docker on Windows

- **Where:** the `.:/app` bind mount in `docker-compose.dev.yml`.
- **Risk:** each file check on the mount costs about 2.6 ms. Pages with many Blade components are slow. `/codex/1/edit` takes 1.1 s, but only 90 ms of that is PHP and SQL.
- **Why it stays:** this cost occurs only in Docker on Windows. On Linux and in the native server, a file check costs almost nothing.
- **Pay off:** move the clone into the WSL2 file system, for example `~/imagoldfish`. This gives the largest gain and needs no app change.

One request makes about 1,500 file checks:

| Source | Checks | Partial fix |
|---|---|---|
| `FileViewFinder` finds each view and component | ~500 | none |
| `__()` looks for a lang file for a key with no match | ~400 | add `lang/en.json` |
| Blade `isExpired` checks if each compiled view is out of date | ~580 | `view.check_cache_timestamps=false`; then each view edit needs `view:clear` |

> [!NOTE]
> `vendor/`, compiled views (`VIEW_COMPILED_PATH=/tmp/views`) and the database are already off the mount, for the same reason (#288, #298).
