import { describe, expect, it } from 'vitest';
import { existsSync, globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/** Confirm that Tailwind scans required sources and loads required plugins. */

const buildAssetsDir = path.join(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
    'public',
    'build',
    'assets',
);

const cssFiles = existsSync(buildAssetsDir) ? globSync(path.join(buildAssetsDir, '*.css')) : [];

describe('built CSS contains utilities from every source the app depends on', () => {
    if (cssFiles.length === 0) {
        it.skip('no build output found — run `npm run build` first (skipped, not failed, so a bare `npm run test` on a fresh clone does not fail for the wrong reason)', () => {});
        return;
    }

    const css = cssFiles.map((file) => readFileSync(file, 'utf8')).join('\n');

    it('scans the vendor pagination view via @source', () => {
        expect(css).toContain('.rtl\\:flex-row-reverse');
    });

    it('loads @tailwindcss/typography via @plugin', () => {
        expect(css).toContain('.prose');
    });

    /**
     * The plugin hard-codes its own grey scale, so `prose` content ignores the active
     * theme even though its container sets a token — bold text stayed near-black and
     * vanished under the dark preset. `app.css` re-points the scale at the role tokens;
     * this asserts the override survived the build, since nothing else would notice.
     */
    it('re-points Tailwind Typography at the role tokens', () => {
        // The built CSS is minified — no space after the colon.
        expect(css).toContain('--tw-prose-bold:var(--color-content)');
        expect(css).toContain('--tw-prose-links:var(--color-link)');
    });

    it('loads @tailwindcss/forms via @plugin', () => {
        expect(css).toContain('input:where([type=checkbox])');
    });

    it('emits a utility for an @theme static role token', () => {
        expect(css).toContain('.border-accent');
    });
});
