import { describe, expect, it } from 'vitest';
import { Editor } from '@tiptap/core';
import { buildExtensions } from '../wysiwyg.js';
import {
    findFallbackWarnings,
    hasMergedTableCell,
    hasResizedImage,
    hasUnmatchedHtmlWrapperTag,
} from './fallbackChecks.js';

/**
 * Tests for expand-tip-tap task 07 — the fallback-warning structural-check
 * list `.specs/planned/2026-07/autosave-with-revisions` §11.5.2 will consume.
 * See `.specs/planned/2026-07/expand-tip-tap/plan/07-fallback-warning-list.md`
 * and `../../.specs/planned/2026-07/expand-tip-tap/expanded/spec.md`'s
 * "Fallback policy" section for the full three-check enumeration.
 */

/** An html-format editor (the 8 RichTextFields fields), matching wysiwyg.js's default branch. */
function htmlEditor(content) {
    return new Editor({ extensions: buildExtensions('html'), content });
}

/** A markdown-format editor (Scene.contents), matching wysiwyg.js's isMarkdown branch. */
function markdownEditor(content) {
    return new Editor({ extensions: buildExtensions('markdown'), content, contentType: 'markdown' });
}

describe('check 1 — merged table cell', () => {
    it('flags a table containing a merged cell (colspan)', () => {
        const source = '<table><tbody><tr><td colspan="2">merged</td></tr><tr><td>a</td><td>b</td></tr></tbody></table>';

        expect(hasMergedTableCell(htmlEditor(source).getJSON())).toBe(true);
    });

    it('flags a table containing a merged cell (rowspan)', () => {
        const source = '<table><tbody><tr><td rowspan="2">merged</td><td>a</td></tr><tr><td>b</td></tr></tbody></table>';

        expect(hasMergedTableCell(htmlEditor(source).getJSON())).toBe(true);
    });

    it('does not flag a plain, unmerged table', () => {
        const source = '<table><tbody><tr><td>1</td><td>2</td></tr></tbody></table>';

        expect(hasMergedTableCell(htmlEditor(source).getJSON())).toBe(false);
    });

    it('does not flag a document with no table at all', () => {
        expect(hasMergedTableCell(htmlEditor('<p>just text</p>').getJSON())).toBe(false);
    });
});

describe('check 2 — resized image', () => {
    it('flags an image with width/height attributes set', () => {
        const source = '<img src="http://example.com/img.png" alt="a" width="100" height="50">';

        expect(hasResizedImage(htmlEditor(source).getJSON())).toBe(true);
    });

    it('does not flag a plain image with no size attributes', () => {
        const source = '<img src="http://example.com/img.png" alt="a">';

        expect(hasResizedImage(htmlEditor(source).getJSON())).toBe(false);
    });

    it('does not flag a document with no image at all', () => {
        expect(hasResizedImage(htmlEditor('<p>just text</p>').getJSON())).toBe(false);
    });
});

describe('check 3 — unmatched HTML wrapper tag', () => {
    it("flags spec.md's own example: a <div> wrapping a <p>", () => {
        const source = '<div class="letter"><p>Dear friend</p></div>';

        expect(hasUnmatchedHtmlWrapperTag(source, htmlEditor(source))).toBe(true);
    });

    it('does not flag a plain paragraph with no wrapper tag', () => {
        const source = '<p>just text</p>';

        expect(hasUnmatchedHtmlWrapperTag(source, htmlEditor(source))).toBe(false);
    });

    it('does not flag a table (its tags all match registered nodes)', () => {
        const source = '<table><tbody><tr><td>1</td></tr></tbody></table>';

        expect(hasUnmatchedHtmlWrapperTag(source, htmlEditor(source))).toBe(false);
    });

    it('does not flag an image tag', () => {
        const source = '<img src="http://example.com/img.png" alt="a">';

        expect(hasUnmatchedHtmlWrapperTag(source, htmlEditor(source))).toBe(false);
    });

    it('does not flag a task list (its rendered <li>/<ul>/<label>/<input>/<span>/<div> are all schema-produced, never "unmatched" in their own output)', () => {
        const source = '<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><label><input type="checkbox" checked><span></span></label><div><p>done</p></div></li></ul>';

        expect(hasUnmatchedHtmlWrapperTag(source, htmlEditor(source))).toBe(false);
    });

    it('does not flag an underline mark (<u> raw-HTML passthrough)', () => {
        const source = '<p><u>text</u></p>';

        expect(hasUnmatchedHtmlWrapperTag(source, markdownEditor(source))).toBe(false);
    });

    it('does not flag a callout block — proving a naive implementation would not mistake its custom node for "unmatched"', () => {
        const htmlSource = '<blockquote data-callout-type="note"><p>hi</p></blockquote>';
        expect(hasUnmatchedHtmlWrapperTag(htmlSource, htmlEditor(htmlSource))).toBe(false);

        // Markdown-format callouts contain no literal HTML tags at all.
        const markdownSource = '> [!NOTE]\ncontent';
        expect(hasUnmatchedHtmlWrapperTag(markdownSource, markdownEditor(markdownSource))).toBe(false);
    });

    it('does not flag any of the task 03 cosmetic-normalisation cases (no literal HTML tags in any of them)', () => {
        expect(hasUnmatchedHtmlWrapperTag('_em_ text', markdownEditor('_em_ text'))).toBe(false);

        const referenceLink = '[text][1]\n\n[1]: http://example.com "title"\n';
        expect(hasUnmatchedHtmlWrapperTag(referenceLink, markdownEditor(referenceLink))).toBe(false);

        const bulletList = '* one\n* two\n';
        expect(hasUnmatchedHtmlWrapperTag(bulletList, markdownEditor(bulletList))).toBe(false);
    });
});

describe('findFallbackWarnings — combined aggregate', () => {
    it('returns an empty array when no check applies', () => {
        const source = '<p>just text</p>';

        expect(findFallbackWarnings({ editor: htmlEditor(source), source })).toEqual([]);
    });

    it('returns only the one applicable case when just one check applies', () => {
        const source = '<img src="http://example.com/img.png" alt="a" width="100" height="50">';

        expect(findFallbackWarnings({ editor: htmlEditor(source), source })).toEqual(['resizedImage']);
    });

    it('returns every applicable case for a document tripping more than one check at once', () => {
        // A merged-cell table (check 1) wrapped in a <div> the schema doesn't
        // claim (check 3) — the div is stripped from the parsed doc (so it
        // never appears there) but is still detectable in the raw source.
        const source = '<div class="wrapper"><table><tbody><tr><td colspan="2">merged</td></tr><tr><td>a</td><td>b</td></tr></tbody></table></div>';
        const editor = htmlEditor(source);

        expect(findFallbackWarnings({ editor, source })).toEqual(
            expect.arrayContaining(['mergedTableCell', 'unmatchedHtmlWrapperTag'])
        );
        expect(findFallbackWarnings({ editor, source })).toHaveLength(2);
    });
});
