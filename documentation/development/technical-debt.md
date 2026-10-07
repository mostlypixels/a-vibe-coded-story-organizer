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

## Static analysis at level 3

- **Where:** `phpstan.neon` sets `level: 3`.
- **Risk:** Larastan does not check argument types or dead code yet. Level 4 reports about 30 errors.
- **Pay off:** raise the level one step at a time. Fix the code. Do not add a baseline or an inline ignore.

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
