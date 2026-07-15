# 03 — Bump composer.json to PHP ^8.5 / Laravel ^13.0

## Scope

Change `composer.json`'s `"php"` constraint and `"laravel/framework"` requirement, run Composer
to resolve the new dependency tree (including `laravel/tinker`, which needs a compatible bump),
and apply whatever the Laravel 13 upgrade guide requires in config/bootstrap files. Does not
include the manual smoke test (task 04) or doc updates (task 05).

## Depends on

Tasks 01–02 (`composer test` green on PHP 8.5.7 + Laravel 12 first, so any failure here is
attributable to the framework bump, not the PHP switch).

## Key decisions already made

* `composer.json`:
  * `"php": "^8.2"` → `"^8.5"`.
  * `"laravel/framework": "^12.0"` → `"^13.0"`.
* Run `composer update` (or `composer require laravel/framework:^13.0`) — let Composer resolve
  the transitive graph itself. Per the `composer why-not laravel/framework 13.20.0` check done
  during planning, the only real blocker besides this project's own constraint was
  `laravel/tinker` v2.11.1 (`illuminate/* ^12.0` cap) — Composer resolves that automatically as
  part of this same command; don't hand-pin a tinker version.
* No proactive bump of `laravel/breeze`, `laravel/sail`, `laravel/pail`, `laravel/pint`,
  `nunomaduro/collision` — only if Composer's own resolution forces it to satisfy Laravel 13.
* Follow the official [Laravel 13 upgrade guide](https://laravel.com/docs/13.x/upgrade). Diff at
  least `config/app.php`, `config/logging.php`, `config/session.php`, `config/filesystems.php`
  against a fresh Laravel 13 skeleton's defaults — don't overwrite this app's customized values,
  only add genuinely new/renamed keys the guide calls out.
* `ezyang/htmlpurifier`, `rampmaster/phpepub`, `secondnetwork/blade-tabler-icons` were confirmed
  non-blocking in the `why-not` check — no action needed on them unless `composer update` itself
  reports a new conflict.

## Docs to consult

* `expanded/architecture.md` — full package/config list.
* `expanded/open-questions.md` #4 — background on the why-not check.

## Tests

* `composer test` must be green after this task. This is the primary regression gate — no new
  test file is expected, since no app behavior is meant to change.
* Pay particular attention to any suite touching `Rule::enum(...)` custom rules (`app/Rules`) and
  the policy/authorization tests (`SceneTest`, `ActTest`, `ChapterTest`, `StoryTest`) — flagged in
  `expanded/testing.md` as the most likely to trip on a Laravel-internals behavior change.
