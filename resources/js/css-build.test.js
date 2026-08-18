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

        // A reference with a fallback remains valid without a declaration.
        const referenced = new Set([...css.matchAll(/var\(\s*(--[a-zA-Z0-9-_]+)\s*\)/g)].map((match) => match[1]));

        // Tailwind can declare internal properties with `@property`.
        const declared = new Set([
            ...[...css.matchAll(/(--[a-zA-Z0-9-_]+)\s*:/g)].map((match) => match[1]),
            ...[...css.matchAll(/@property\s+(--[a-zA-Z0-9-_]+)/g)].map((match) => match[1]),
        ]);

        const dangling = [...referenced].filter((name) => !declared.has(name)).sort();

        expect(dangling, `dangling var() reference(s) with no matching declaration in the built CSS: ${dangling.join(', ')}`).toEqual([]);
    });
});
