// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import viteConfig from '../../vite.config.js';

/** Confirm that Vite serves root-relative public assets in development. */

const projectRoot = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const appCss = readFileSync(path.join(projectRoot, 'resources', 'css', 'app.css'), 'utf8');

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
