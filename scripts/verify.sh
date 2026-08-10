#!/usr/bin/env bash
# verify.sh — run the project's whole green-tree gate in one call.
#
# Runs, in order: the PHP suite (`composer test`), the JS suite (`npm run test`)
# and the formatter check (`composer lint -- --test`). All three always run, so
# one call reports every problem instead of stopping at the first — then it
# prints a one-line-per-tool summary. Full output of a passing tool is thrown
# away; a failing tool gets its last lines printed and its log file kept.
#
# These are the canonical commands from CLAUDE.md → Commands. This script is the
# only place they are spelled out; callers must not hardcode them.
#
# Usage: scripts/verify.sh [--filter <pattern>] [--no-js] [--no-lint]
#
#   --filter <pattern>  run `php artisan test --filter <pattern>` instead of the
#                       full parallel suite, and skip the JS suite (a PHP filter
#                       says nothing about JS). For a fast loop on one test.
#   --no-js             skip the JS suite.
#   --no-lint           skip the formatter check.
#
# Exit codes:
#   0 — every tool that ran passed
#   1 — at least one failed (its output tail and log path are printed)
#   2 — bad arguments
#
# Callers: .claude/agents/plan-implementer.md, .claude/skills/ship-plan,
# .claude/skills/ship-pr, .claude/skills/run-imagoldfish.
set -euo pipefail

usage() {
    echo "usage: scripts/verify.sh [--filter <pattern>] [--no-js] [--no-lint]" >&2
    exit 2
}

FILTER=""
RUN_JS=1
RUN_LINT=1

while [ $# -gt 0 ]; do
    case "$1" in
        --filter)
            [ $# -ge 2 ] && [ -n "$2" ] || usage
            FILTER="$2"
            # A PHP filter proves nothing about the JS suite, and running it
            # would defeat the point of a fast single-test loop.
            RUN_JS=0
            shift 2
            ;;
        --no-js) RUN_JS=0; shift ;;
        --no-lint) RUN_LINT=0; shift ;;
        *) usage ;;
    esac
done

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

LOG_DIR="$(mktemp -d)"
TAIL_LINES=40

# Names and outcomes accumulate in step order for the closing summary.
summary=""
failed=0

# run_step <label> <log-name> <command...>
run_step() {
    label="$1"
    log="$LOG_DIR/$2.log"
    shift 2

    echo "--- $label"
    if "$@" > "$log" 2>&1; then
        summary="$summary\nPASS  $label$(status_line "$log")"
    else
        failed=1
        echo "$label FAILED — last $TAIL_LINES lines:" >&2
        tail -n "$TAIL_LINES" "$log" >&2
        summary="$summary\nFAIL  $label  (full output: $log)"
    fi
}

# Pull the tool's own count line out of its log, so the summary carries real
# numbers ("Tests: 412 passed") rather than a bare PASS.
status_line() {
    # The runners colour their summary line, which puts escape sequences between
    # "Tests:" and the number, so strip the colours before matching.
    # `artisan test` reports "Tests:  N passed", paratest (the parallel runner
    # `composer test` uses) reports "OK (N tests, M assertions)" instead.
    counts="$(sed -e "s/$(printf '\033')\[[0-9;]*m//g" "$1" | tr -d '\r' \
        | grep -aoE '(Tests|Test Files|Assertions)[: ]+[0-9].*|OK \([0-9]+ tests[^)]*\)' \
        | tail -n 1 || true)"
    # Must end on a zero status: it is called inside a command substitution, and
    # `set -e` would take a non-zero return from that as the script failing.
    if [ -n "$counts" ]; then
        printf '  (%s)' "$counts"
    fi
    return 0
}

if [ -n "$FILTER" ]; then
    run_step "PHP tests (--filter $FILTER)" php-tests php artisan test --filter "$FILTER"
else
    run_step "PHP tests" php-tests composer test
fi

if [ "$RUN_JS" -eq 1 ]; then
    run_step "JS tests" js-tests npm run test
fi

if [ "$RUN_LINT" -eq 1 ]; then
    run_step "Lint" lint composer lint -- --test
fi

echo ""
echo "=== verify.sh summary"
printf '%b\n' "${summary#\\n}"

if [ "$failed" -eq 0 ]; then
    rm -rf "$LOG_DIR"
    echo "All green."
fi

exit "$failed"
