// @vitest-environment node
//
// Overrides the suite-wide jsdom (vitest.config.js). Importing the Vite config
// pulls in esbuild, and esbuild refuses to load under jsdom: it asserts that
// `new TextEncoder().encode('')` is a `Uint8Array`, which jsdom's own
// TextEncoder — built in a second JS realm — fails. This file touches no DOM.
import { describe, expect, it } from 'vitest';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import viteConfig from '../../vite.config.js';

/**
 * Guard for the root-relative asset URLs in `resources/css/app.css`.
 *
 * `@font-face` asks for `/fonts/*.woff2`, which the browser resolves against the
 * origin that served the stylesheet. In a build that is the app itself, so the
 * files come from `public/fonts`. Under `npm run dev` it is the Vite server, and
 * Vite serves `public/` only when `publicDir` is set — laravel-vite-plugin turns
 * it off by default.
 *
 * > [!WARNING]
 * > This failure is silent in both directions. A missing font makes the page
 * > render in the fallback family because `font-display: swap` expects exactly
 * > that, and the console error appears only after the swap. The production
 * > build keeps working the whole time, so nothing in CI notices.
 */

const projectRoot = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const appCss = readFileSync(path.join(projectRoot, 'resources', 'css', 'app.css'), 'utf8');

/** Every `url(/…)` in the stylesheet, deduplicated. Quotes are optional in CSS. */
const rootRelativeUrls = [...new Set(
    Array.from(appCss.matchAll(/url\(\s*['"]?(\/[^'")]+)['"]?\s*\)/g)).map((match) => match[1]),
)];

describe('root-relative asset URLs in app.css', () => {
    it('finds some, so the assertions below are not passing on an empty list', () => {
        expect(rootRelativeUrls.length).toBeGreaterThan(0);
    });

    it('serves the directory holding them from the dev server', () => {
        // `false` is laravel-vite-plugin's default and the bug: the dev server
        // then 404s every URL below while the build serves them all.
        expect(viteConfig.publicDir).toBeTypeOf('string');
    });

    it('does not copy that directory into the build output nested inside itself', () => {
        expect(viteConfig.build.copyPublicDir).toBe(false);
    });

    it.each(rootRelativeUrls)('resolves %s to a file in that directory', (url) => {
        expect(existsSync(path.join(projectRoot, viteConfig.publicDir, url))).toBe(true);
    });
});
