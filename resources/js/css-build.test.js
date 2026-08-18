import { describe, expect, it } from 'vitest';
import { existsSync, globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/** Detect CSS variable references that have no declaration. */

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
