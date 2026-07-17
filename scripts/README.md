# Workflow scripts

Reusable bash scripts extracted from the project's skills and agents by the
`extract-tools-and-commands` skill. Check here **before** inlining a command sequence in
a skill. The contract every script follows (arguments only, repo-root-relative paths,
secrets from env, `set -euo pipefail`, header naming its callers) is documented in
`.claude/skills/extract-tools-and-commands/SKILL.md`.

| Script | Purpose | Called by |
|--------|---------|-----------|
| `spec-locate.sh <name>` | Locate a feature folder under `.specs/` (`<status><TAB><path>` per match, earliest-lifecycle-first on collision) | mp-spec-expander, plan-tasks, ship-plan, plan-implementer, spec-advance.sh, plan-next-task.sh |
| `spec-advance.sh <name> <status>` | Advance a feature one lifecycle stage: validate transition, stamp frontmatter status + date, auto-suffix on name collision, `git mv` to `.specs/<status>/<YYYY-MM>/` | mp-spec-expander, plan-tasks, ship-plan |
| `plan-next-task.sh <name>` | List a feature's unimplemented `NN-*.md` plan tasks in numeric order (exit 2 = plan complete, exit 1 = no plan) | ship-plan, plan-implementer |
| `serve-app.sh [--port N]` | Pre-flight-check (stale `public/hot`, missing build, pending migrations), then start `php artisan serve` in the background with a PID file; idempotent | run-imagoldfish |
| `stop-app.sh` | Kill the exact dev-server PID recorded by serve-app.sh and remove the PID file; idempotent | run-imagoldfish |
| `pr-land.sh <title> <body-file>` | Land the current feature branch on master: push, open PR, arm squash auto-merge, watch CI, confirm MERGED, update local master | ship-pr |

There is also one artisan command extracted from the skills: `php artisan spec:draft`
(scaffolds a stage-1 draft spec; prompts for missing input when run interactively) —
see `app/Console/Commands/SpecDraftCommand.php` and the draft-spec skill.
