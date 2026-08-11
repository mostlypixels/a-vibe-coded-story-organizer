#!/usr/bin/env bash
# fetch-fonts.sh — download the bundled-font woff2 files into public/fonts/.
#
# The font files themselves are checked into the repo, not fetched at build
# time — self-hosting means the app works air-gapped, and a CDN outage must
# not block a build. This script is provenance (where each file came from)
# and a re-run path (a corrupted or missing file), not a build step.
#
# Sources are pinned to a fontsource npm package version so a re-run
# reproduces the same bytes. Each family ships one variable woff2 per
# style/subset combination (font-weight 200–900 in one file), covering the
# 200–700 range the @font-face blocks in resources/css/app.css declare.
#
# Lexend has no italic design upstream (fontsource lists only a "normal"
# style for it) — it gets roman files only, unlike the other three families.
#
# Usage: scripts/fetch-fonts.sh
#
# Exit codes:
#   0 — every file downloaded
#   1 — a download failed (curl's exit status is propagated)
#   2 — bad arguments
#
# Callers: humans re-provisioning public/fonts/; documentation/fonts.md.
set -euo pipefail

if [ $# -ne 0 ]; then
    echo "usage: scripts/fetch-fonts.sh" >&2
    exit 2
fi

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

FONTSOURCE_VERSION="5.3.0"
DEST_DIR="public/fonts"
mkdir -p "$DEST_DIR"

# slug|package|style|subset|filename
FONTS=(
    "inter|inter|normal|latin|inter-latin-normal.woff2"
    "inter|inter|normal|latin-ext|inter-latin-ext-normal.woff2"
    "inter|inter|italic|latin|inter-latin-italic.woff2"
    "inter|inter|italic|latin-ext|inter-latin-ext-italic.woff2"

    "lexend|lexend|normal|latin|lexend-latin-normal.woff2"
    "lexend|lexend|normal|latin-ext|lexend-latin-ext-normal.woff2"

    "literata|literata|normal|latin|literata-latin-normal.woff2"
    "literata|literata|normal|latin-ext|literata-latin-ext-normal.woff2"
    "literata|literata|italic|latin|literata-latin-italic.woff2"
    "literata|literata|italic|latin-ext|literata-latin-ext-italic.woff2"

    "source-serif-4|source-serif-4|normal|latin|source-serif-4-latin-normal.woff2"
    "source-serif-4|source-serif-4|normal|latin-ext|source-serif-4-latin-ext-normal.woff2"
    "source-serif-4|source-serif-4|italic|latin|source-serif-4-latin-italic.woff2"
    "source-serif-4|source-serif-4|italic|latin-ext|source-serif-4-latin-ext-italic.woff2"

    "jetbrains-mono|jetbrains-mono|normal|latin|jetbrains-mono-latin-normal.woff2"
    "jetbrains-mono|jetbrains-mono|normal|latin-ext|jetbrains-mono-latin-ext-normal.woff2"
    "jetbrains-mono|jetbrains-mono|italic|latin|jetbrains-mono-latin-italic.woff2"
    "jetbrains-mono|jetbrains-mono|italic|latin-ext|jetbrains-mono-latin-ext-italic.woff2"
)

for entry in "${FONTS[@]}"; do
    IFS='|' read -r _slug package style subset filename <<< "$entry"
    url="https://cdn.jsdelivr.net/npm/@fontsource-variable/${package}@${FONTSOURCE_VERSION}/files/${package}-${subset}-wght-${style}.woff2"
    echo "Fetching ${filename} <- ${url}"
    curl -fsSL "$url" -o "${DEST_DIR}/${filename}"
done

echo "Done. $(( ${#FONTS[@]} )) files written to ${DEST_DIR}."
