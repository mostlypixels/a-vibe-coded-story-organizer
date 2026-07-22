import { defineConfig } from 'vitest/config';

/**
 * Vitest configuration for the project's JS unit-test tier (introduced by the
 * expand-tip-tap feature, task 03 — see documentation/rich-text.md).
 *
 * `environment: 'jsdom'` is required, not optional: Tiptap's `Editor.getHTML()`
 * calls ProseMirror's `DOMSerializer`, which reaches for `window.document` to
 * build the output — plain Node has no such global. `getMarkdown()` alone does
 * not need it, but tests in this suite exercise both directions.
 */
export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
    },
});
