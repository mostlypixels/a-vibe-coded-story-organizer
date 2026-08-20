# Workflow scripts

Reusable bash scripts extracted from the project's skills and agents by the
`extract-tools-and-commands` skill. Check here **before** inlining a command sequence in
a skill. The contract every script follows (arguments only, repo-root-relative paths,
secrets from env, `set -euo pipefail`, header naming its callers) is documented in
`.claude/skills/extract-tools-and-commands/SKILL.md`.

| Script | Purpose | Called by |
|--------|---------|-----------|
| `spec-locate.sh <name>` | Locate a feature folder under `.specs/` (`<status><TAB><path>` per match, earliest-lifecycle-first on collision) | mp-expand-spec, mp-plan-tasks, ship-plan, plan-implementer, spec-advance.sh, plan-next-task.sh |
| `spec-advance.sh <name> <status>` | Advance a feature one lifecycle stage: validate transition, stamp frontmatter status + date, auto-suffix on name collision, `git mv` to `.specs/<status>/<YYYY-MM>/` | mp-expand-spec, mp-plan-tasks, ship-plan |
| `plan-next-task.sh <name>` | List a feature's unimplemented `NN-*.md` plan tasks in numeric order (exit 2 = plan complete, exit 1 = no plan) | ship-plan, plan-implementer |
| `verify.sh [--filter P] [--no-js] [--no-lint]` | Run the whole green-tree gate in one call — PHP suite, JS suite, Pint check — all three even if one fails, then one summary line each with counts (exit 1 = something failed) | plan-implementer, ship-plan, ship-pr, run-imagoldfish |
| `probe-test.sh '<php>'` | Answer a scratch question with a throwaway feature test (in-memory DB, `RefreshDatabase`), run then deleted — the tinker-free probe CLAUDE.md requires | humans and agents debugging; CLAUDE.md → Testing |
| `assets-state.sh` | Report whether the app would serve the build: stale `public/hot`, missing `public/build`, dev database behind migrations (exit 1 = at least one) | serve-app.sh, plan-implementer, run-imagoldfish |
| `serve-app.sh [--port N]` | Refuse if another server holds the port (e.g. the Docker stack), pre-flight-check via `assets-state.sh`, then start `php artisan serve` in the background with a PID file; idempotent | run-imagoldfish |
| `stop-app.sh` | Kill the exact dev-server PID recorded by serve-app.sh and remove the PID file; idempotent | run-imagoldfish |
| `claude-usage.sh [--text\|--raw]` | Report Claude Code session/week limit usage as JSON (default); failures print one word (`unavailable`, `unparseable`) and exit 1 | plan-implementer |
| `pr-land.sh <title> <body-file>` | Land the current feature branch on master: push, open PR, stamp the PR number onto the changelog heading, arm squash auto-merge, watch CI, merge, confirm MERGED, update local master | ship-pr |

There is also one artisan command extracted from the skills: `php artisan spec:draft`
(scaffolds a stage-1 draft spec; prompts for missing input when run interactively) —
see `app/Console/Commands/SpecDraftCommand.php` and the mp-draft-spec skill.
