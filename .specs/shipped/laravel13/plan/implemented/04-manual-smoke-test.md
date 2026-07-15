# 04 — Manual smoke test on the upgraded stack

## Scope

Exercise the app by hand (or via the `run-imagoldfish` skill) on PHP 8.5 + Laravel 13 to catch
anything `composer test` wouldn't — this is a verification task, not a code change. No files
should need to change here; if one does, it means task 03 missed something and should be
revisited rather than papered over here.

## Depends on

Task 03 (`composer test` already green on the full upgraded stack).

## Key decisions already made

None beyond the flows to check — this task is pure verification, per `expanded/testing.md`.

## Docs to consult

* `expanded/testing.md` — the manual smoke-test checklist this task executes.

## Steps / "tests"

1. Log in.
2. Open a project's story overview (act → chapter → scene tree) — the nested eager-load path
   called out in `CLAUDE.md`.
3. Edit and save a scene (Form Request + policy + markdown rule).
4. Export an epub (`rampmaster/phpepub` path).
5. Check `/robots.txt` renders.

If any step fails or behaves differently than on the pre-upgrade stack, that's a regression to
fix in task 03's scope (config diff or a missed upgrade-guide step) — don't work around it here.
