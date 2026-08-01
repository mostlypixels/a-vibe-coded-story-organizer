import { describe, expect, it } from 'vitest';
import { existsSync, globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Guard for the Tailwind 4 migration (tailwind-4 task 07).
 *
 * Tailwind 4 only emits a utility for a class if its content scanner actually reaches the
 * source file using it. A misconfigured `@source`/`@plugin` line drops those utilities
 * silently — `npm run build` still succeeds, nothing errors, the page just renders unstyled
 * the next time someone opens it. This test asserts the built CSS contains rules proving each
 * source the app depends on was actually scanned:
 *
 * - `.rtl\:flex-row-reverse` only exists in Laravel's vendor pagination view
 *   (`vendor/laravel/framework/.../Pagination/resources/views/tailwind.blade.php`, used by
 *   `resources/views/revisions/index.blade.php` via `->links()`) — proves the `@source`
 *   directive in `resources/css/app.css` reaches into `vendor/`.
 * - `.prose` proves `@tailwindcss/typography` loaded via `@plugin`.
 * - the `input:where([type=checkbox])` reset proves `@tailwindcss/forms` loaded via `@plugin`.
 * - `.border-nav-active` proves `@theme` custom colours (task 06's `--color-nav-active`)
 *   still emit utility classes.
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

    it('loads @tailwindcss/forms via @plugin', () => {
        expect(css).toContain('input:where([type=checkbox])');
    });

    it('emits a utility for the @theme nav-active custom colour', () => {
        expect(css).toContain('.border-nav-active');
    });
});
