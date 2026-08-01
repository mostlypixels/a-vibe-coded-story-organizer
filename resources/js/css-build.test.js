import { describe, expect, it } from 'vitest';
import { existsSync, globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Guard for the Tailwind 4 migration (tailwind-4 task 02).
 *
 * Tailwind 4 still resolves old `theme()` dot-path calls that were never rewritten to
 * `var(--…)`, and it still emits any custom property that hand-written CSS references via
 * `var()` — but a `theme()` call rewritten to a *misspelled* variable name compiles clean and
 * silent. The browser drops the declaration at compute time; nothing in `composer test` or
 * `npm run build` notices. This test is the only mechanical check for that failure mode: it
 * builds the set of every `var(--x)` reference in the emitted stylesheet and the set of every
 * `--x:` declaration, and fails if a reference has no matching declaration.
 *
 * KNOWN LIMITATION — read before trusting this test as complete coverage: it only catches
 * *dangling* references (a variable that is never declared anywhere). It does NOT catch a
 * `theme()` call rewritten to a variable that exists but is the WRONG one (e.g.
 * `theme('spacing.2')` becoming `var(--text-sm)` instead of `var(--spacing-2)`) — that
 * resolves fine and passes this test silently. That class of error is caught only by the
 * manual `theme()` rewrite audit (task 04) and the browser pass (task 09). Treat this test as
 * a floor, not a ceiling.
 */

const buildAssetsDir = path.join(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
    'public',
    'build',
    'assets',
);

const cssFiles = existsSync(buildAssetsDir) ? globSync(path.join(buildAssetsDir, '*.css')) : [];

describe('built CSS custom property resolution', () => {
    if (cssFiles.length === 0) {
        it.skip('no build output found — run `npm run build` first (skipped, not failed, so a bare `npm run test` on a fresh clone does not fail for the wrong reason)', () => {});
        return;
    }

    it('declares every custom property it references via var(--…)', () => {
        const css = cssFiles.map((file) => readFileSync(file, 'utf8')).join('\n');

        // Only `var(--foo)` with NO fallback is at risk of silently dropping — that is the
        // exact failure mode this guard exists for. `var(--foo, fallback)` is a deliberate,
        // working extension point (Tailwind's own preflight ships several, e.g.
        // `var(--default-font-feature-settings, normal)`, always undeclared unless a project
        // opts in): the browser uses the fallback, nothing is dropped, so it is not "dangling"
        // in the sense that matters here.
        const referenced = new Set([...css.matchAll(/var\(\s*(--[a-zA-Z0-9-_]+)\s*\)/g)].map((match) => match[1]));

        // `--foo:` as a declaration, plus `@property --foo` (Tailwind emits its own `--tw-*`
        // internals this way, e.g. `@property --tw-blur { syntax: "*"; ... }`, with no `:`
        // directly after the name).
        const declared = new Set([
            ...[...css.matchAll(/(--[a-zA-Z0-9-_]+)\s*:/g)].map((match) => match[1]),
            ...[...css.matchAll(/@property\s+(--[a-zA-Z0-9-_]+)/g)].map((match) => match[1]),
        ]);

        const dangling = [...referenced].filter((name) => !declared.has(name)).sort();

        expect(dangling, `dangling var() reference(s) with no matching declaration in the built CSS: ${dangling.join(', ')}`).toEqual([]);
    });
});
