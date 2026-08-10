#!/usr/bin/env bash
# probe-test.sh — answer a throwaway question about the app without touching the
# dev database.
#
# CLAUDE.md forbids probing with `php artisan tinker`: tinker runs on the default
# connection (database/database.sqlite — real data), so a scratch script that
# creates models leaves them behind. The prescribed alternative is a temporary
# feature test, run, then deleted. This script is that ritual, so the "then
# deleted" part cannot be forgotten: phpunit.xml forces an in-memory SQLite
# database, and RefreshDatabase plus the model factories come for free.
#
# The snippet becomes the body of a test method, so $this is the TestCase —
# $this->actingAs(...), $this->get(...) and the assertion helpers all work.
# It gets no `use` imports: name classes in full (\App\Models\Project::factory()).
# Print what you want to see with dump().
#
# Usage:
#   scripts/probe-test.sh '<php snippet>'
#   scripts/probe-test.sh --file <path-to-snippet>
#   scripts/probe-test.sh --keep '<php snippet>'   # leave the test file in place
#
# Example:
#   scripts/probe-test.sh 'dump(\App\Models\Project::factory()->create()->scenes()->sum("word_count"));'
#
# Exit codes:
#   0 — the probe ran and passed
#   1 — the probe failed or errored (PHPUnit output is printed as-is)
#   2 — bad arguments, or a scratch probe file is already there
#
# Callers: humans and agents debugging in this repo; the "scratch verification"
# rule in CLAUDE.md → Testing.
set -euo pipefail

usage() {
    echo "usage: scripts/probe-test.sh [--keep] ('<php snippet>' | --file <path>)" >&2
    exit 2
}

KEEP=0
SNIPPET=""
SNIPPET_FILE=""

while [ $# -gt 0 ]; do
    case "$1" in
        --keep) KEEP=1; shift ;;
        --file)
            [ $# -ge 2 ] && [ -n "$2" ] || usage
            SNIPPET_FILE="$2"
            shift 2
            ;;
        --*) usage ;;
        *)
            [ -z "$SNIPPET" ] || usage
            SNIPPET="$1"
            shift
            ;;
    esac
done

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

if [ -n "$SNIPPET_FILE" ]; then
    [ -z "$SNIPPET" ] || usage
    [ -f "$SNIPPET_FILE" ] || { echo "probe-test.sh: no such file: $SNIPPET_FILE" >&2; exit 2; }
    SNIPPET="$(cat "$SNIPPET_FILE")"
fi

[ -n "$SNIPPET" ] || usage

TEST_FILE="tests/Feature/ScratchProbeTest.php"

# A leftover file means a previous --keep run, or a probe someone is still
# editing. Overwriting it would destroy their work silently.
if [ -e "$TEST_FILE" ]; then
    echo "probe-test.sh: $TEST_FILE already exists — delete it (or finish with it) first." >&2
    exit 2
fi

# The trap is the whole point: the file goes away on success, on failure, and on
# an interrupt alike. --keep disarms it.
# shellcheck disable=SC2329  # invoked by the trap below, not by name.
cleanup() {
    [ "$KEEP" -eq 1 ] || rm -f "$TEST_FILE"
}
trap cleanup EXIT

cat > "$TEST_FILE" <<PHP
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Scratch probe written by scripts/probe-test.sh. Not part of the suite. */
class ScratchProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe(): void
    {
$SNIPPET

        // Keeps PHPUnit from marking a probe with no assertions as risky.
        \$this->assertTrue(true);
    }
}
PHP

status=0
php artisan test --filter ScratchProbeTest || status=1

if [ "$KEEP" -eq 1 ]; then
    echo ""
    echo "Kept $TEST_FILE — delete it when done (it must not be committed)."
fi

exit "$status"
