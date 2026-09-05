import { afterEach, describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Editor } from '@tiptap/core';
import { buildExtensions, buildSlashItems, registerWysiwyg } from './wysiwyg.js';

const projectRoot = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

function readSource(relative) {
    return readFileSync(path.join(projectRoot, relative), 'utf8');
}

/** Read a flat list of quoted lower-case names out of a PHP or JS constant. */
function sourceList(source, constant) {
    const after = source.split(`${constant} = [`)[1];

    if (after === undefined) {
        throw new Error(`could not find ${constant}`);
    }

    return [...after.split(']')[0].matchAll(/'([a-z]+)'/g)].map((found) => found[1]);
}

function registryList(constant) {
    return sourceList(readSource('app/Support/RichTextFields.php'), constant);
}

function markdownEditor(content) {
    return new Editor({ extensions: buildExtensions('markdown'), content, contentType: 'markdown' });
}

function htmlEditor(content) {
    return new Editor({ extensions: buildExtensions('html'), content });
}

function firstCellTextPosition(editor) {
    let position = null;
    editor.state.doc.descendants((node, pos) => {
        if (position === null && node.isText) position = pos + 1;
        return position === null;
    });
    return position;
}

describe('table round-trip', () => {
    it('markdown table content is stable across a round trip', () => {
        const source = '| a | b |\n| --- | --- |\n| 1 | 2 |\n';
        const first = markdownEditor(source).getMarkdown();
        const second = markdownEditor(first).getMarkdown();

        expect(second).toBe(first);
        expect(first).toContain('a');
        expect(first).toContain('1');
    });

    it('html table content is stable across a round trip', () => {
        const source = '<table><tbody><tr><td>1</td><td>2</td></tr></tbody></table>';
        const first = htmlEditor(source).getHTML();
        const second = htmlEditor(first).getHTML();

        expect(second).toBe(first);
        expect(first).toContain('<table');
        expect(first).toContain('1');
    });

    it('a merged table cell survives in html format but loses the merge in markdown format', () => {
        const merged = '<table><tbody><tr><td colspan="2">merged</td></tr><tr><td>a</td><td>b</td></tr></tbody></table>';

        const htmlOut = htmlEditor(merged).getHTML();
        expect(htmlOut).toContain('colspan="2"');

        const markdownOut = markdownEditor(merged).getMarkdown();
        expect(markdownOut).not.toContain('colspan');
        expect(markdownOut).toContain('merged');
    });
});

describe('image round-trip', () => {
    it('markdown image content is stable across a round trip', () => {
        const source = '![alt](http://example.com/img.png "title")';
        const first = markdownEditor(source).getMarkdown();
        const second = markdownEditor(first).getMarkdown();

        expect(second).toBe(first);
        expect(first).toBe(source);
    });

    it('html image content is stable across a round trip', () => {
        const source = '<img src="http://example.com/img.png" alt="alt" title="title">';
        const first = htmlEditor(source).getHTML();
        const second = htmlEditor(first).getHTML();

        expect(second).toBe(first);
        expect(first).toContain('<img');
    });

    it('a resized image survives in html format but loses width/height in markdown format', () => {
        const resized = '<img src="http://example.com/img.png" alt="a" width="100" height="50">';

        const htmlOut = htmlEditor(resized).getHTML();
        expect(htmlOut).toContain('width="100"');
        expect(htmlOut).toContain('height="50"');

        const markdownOut = markdownEditor(resized).getMarkdown();
        expect(markdownOut).not.toContain('width');
        expect(markdownOut).not.toContain('100');
    });
});

describe('task list round-trip', () => {
    it('markdown task list content is stable across a round trip', () => {
        const source = '- [ ] todo\n- [x] done\n';
        const first = markdownEditor(source).getMarkdown();
        const second = markdownEditor(first).getMarkdown();

        expect(second).toBe(first);
        expect(first).toContain('[ ] todo');
        expect(first).toContain('[x] done');
    });

    it('html task list content is stable across a round trip', () => {
        const source = '<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><label><input type="checkbox" checked><span></span></label><div><p>done</p></div></li></ul>';
        const first = htmlEditor(source).getHTML();
        const second = htmlEditor(first).getHTML();

        expect(second).toBe(first);
        expect(first).toContain('data-type="taskList"');
        expect(first).toContain('data-checked="true"');
    });
});

describe('normalisation — cosmetic, not lossy', () => {
    it('underscore emphasis normalizes to asterisk emphasis, meaning unchanged', () => {
        const out = markdownEditor('_em_ text').getMarkdown();

        expect(out).toContain('*em*');
    });

    it('reference-style links normalize to inline links, url and text unchanged', () => {
        const source = '[text][1]\n\n[1]: http://example.com "title"\n';
        const out = markdownEditor(source).getMarkdown();

        expect(out).toContain('[text](http://example.com "title")');
    });

    it('bullet marker style may change without losing list items', () => {
        const source = '* one\n* two\n';
        const out = markdownEditor(source).getMarkdown();

        expect(out).toMatch(/[-*]\s+one/);
        expect(out).toMatch(/[-*]\s+two/);
    });
});

describe('already-safe constructs — regression guards', () => {
    it('nested blockquotes preserve depth', () => {
        const source = '> outer\n> > inner\n';
        const out = markdownEditor(source).getMarkdown();

        expect(out).toContain('> outer');
        expect(out).toContain('> > inner');
    });

    it('hard line breaks preserve the break', () => {
        const source = 'line one  \nline two\n';
        const out = markdownEditor(source).getMarkdown();

        expect(out).toMatch(/line one {2}\n/);
        expect(out).toContain('line two');
    });
});

describe('image resize — HTML-mode only', () => {
    const imageOptions = (format) =>
        new Editor({ extensions: buildExtensions(format), content: '<p></p>' }).extensionManager.extensions.find(
            (extension) => extension.name === 'image'
        ).options;

    it('resize is enabled for html-format fields', () => {
        expect(imageOptions('html').resize).toEqual(expect.objectContaining({ enabled: true }));
    });

    it('resize stays off for markdown-format fields', () => {
        expect(imageOptions('markdown').resize).toBe(false);
    });
});

describe('table merge/split — HTML-mode only', () => {
    it('mergeCells/splitCell commands exist in both formats (the Table extension itself is unconditional)', () => {
        const html = htmlEditor('<p></p>');
        const markdown = markdownEditor('text');

        expect(typeof html.commands.mergeCells).toBe('function');
        expect(typeof html.commands.splitCell).toBe('function');
        expect(typeof markdown.commands.mergeCells).toBe('function');
        expect(typeof markdown.commands.splitCell).toBe('function');
    });

    it('a merged cell round-trips through html format without a style or colgroup leaking into the saved output', () => {
        const merged = '<table><tbody><tr><td colspan="2">merged</td></tr><tr><td>a</td><td>b</td></tr></tbody></table>';
        const out = htmlEditor(merged).getHTML();

        expect(out).toContain('colspan="2"');
        expect(out).not.toContain('style=');
        expect(out).not.toContain('<colgroup');
        expect(out).not.toContain('<col ');
    });
});

describe('table row/column add and remove — both formats', () => {
    const twoByTwo = '<table><tbody><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></tbody></table>';

    it('addRowAfter grows a 2x2 html table to three rows and the result round-trips', () => {
        const editor = htmlEditor(twoByTwo);
        editor.chain().focus().setTextSelection(2).addRowAfter().run();

        const out = editor.getHTML();
        expect((out.match(/<tr>/g) || []).length).toBe(3);
        expect(htmlEditor(out).getHTML()).toBe(out);
    });

    it('deleteRow shrinks a 2x2 html table to one row and the result round-trips', () => {
        const editor = htmlEditor(twoByTwo);
        editor.chain().focus().setTextSelection(2).deleteRow().run();

        const out = editor.getHTML();
        expect((out.match(/<tr>/g) || []).length).toBe(1);
        expect(htmlEditor(out).getHTML()).toBe(out);
    });

    it('addColumnAfter grows a 2x2 html table to three columns per row and the result round-trips', () => {
        const editor = htmlEditor(twoByTwo);
        editor.chain().focus().setTextSelection(2).addColumnAfter().run();

        const out = editor.getHTML();
        expect((out.match(/<td/g) || []).length).toBe(6);
        expect(htmlEditor(out).getHTML()).toBe(out);
    });

    it('deleteColumn shrinks a 2x2 html table to one column per row and the result round-trips', () => {
        const editor = htmlEditor(twoByTwo);
        editor.chain().focus().setTextSelection(2).deleteColumn().run();

        const out = editor.getHTML();
        expect((out.match(/<td/g) || []).length).toBe(2);
        expect(htmlEditor(out).getHTML()).toBe(out);
    });

    it('addRowAfter/deleteRow/addColumnAfter/deleteColumn commands exist in markdown format too', () => {
        const markdown = markdownEditor('| a | b |\n| --- | --- |\n| 1 | 2 |\n');

        expect(typeof markdown.commands.addRowAfter).toBe('function');
        expect(typeof markdown.commands.deleteRow).toBe('function');
        expect(typeof markdown.commands.addColumnAfter).toBe('function');
        expect(typeof markdown.commands.deleteColumn).toBe('function');
    });

    it('addColumnAfter on a markdown table grows the GFM row/separator to three columns and round-trips', () => {
        const editor = markdownEditor('| a | b |\n| --- | --- |\n| 1 | 2 |\n');
        editor.chain().focus().setTextSelection(firstCellTextPosition(editor)).addColumnAfter().run();

        const out = editor.getMarkdown();
        const headerRow = out.split('\n').find((line) => line.includes('a'));
        expect(headerRow.split('|').length).toBe(5); // '', a, '', b, '' -> 3 columns
        // Trailing-newline count is a known cosmetic wobble across re-hydration
        // (see the "normalisation — cosmetic, not lossy" describe block above);
        // trim before comparing so this test pins content, not whitespace count.
        expect(markdownEditor(out).getMarkdown().trim()).toBe(out.trim());
    });
});

describe('underline & strikethrough round-trip in markdown format', () => {
    it('a document containing <u>text</u> round-trips through hydrate → getMarkdown() → re-hydrate unchanged', () => {
        const source = '<p><u>text</u></p>';
        const first = markdownEditor(source).getMarkdown();
        const second = markdownEditor(first).getMarkdown();

        expect(first).toContain('<u>text</u>');
        expect(second).toBe(first);
    });

    it('a document containing ~~text~~ round-trips through hydrate → getMarkdown() → re-hydrate unchanged', () => {
        const source = '~~text~~';
        const first = markdownEditor(source).getMarkdown();
        const second = markdownEditor(first).getMarkdown();

        expect(first).toContain('~~text~~');
        expect(second).toBe(first);
    });

    it("the slash menu's markdown-format item list includes Underline and Strikethrough (regression guard against mdHide reappearing)", () => {
        const titles = buildSlashItems('markdown', () => {}, () => {}).map((item) => item.title);

        expect(titles).toContain('Underline');
        expect(titles).toContain('Strikethrough');
    });

    it('the slash menu offers Subscript and Superscript, matching the toolbar typography dropdown', () => {
        for (const format of ['html', 'markdown']) {
            const titles = buildSlashItems(format, () => {}, () => {}).map((item) => item.title);

            expect(titles).toContain('Subscript');
            expect(titles).toContain('Superscript');
        }
    });
});

describe('subscript & superscript round-trip in markdown format', () => {
    it('a document containing <sub>text</sub> round-trips through hydrate → getMarkdown() → re-hydrate unchanged', () => {
        const source = '<p><sub>text</sub></p>';
        const first = markdownEditor(source).getMarkdown();
        const second = markdownEditor(first).getMarkdown();

        expect(first).toContain('<sub>text</sub>');
        expect(second).toBe(first);
    });

    it('a document containing <sup>text</sup> round-trips through hydrate → getMarkdown() → re-hydrate unchanged', () => {
        const source = '<p><sup>text</sup></p>';
        const first = markdownEditor(source).getMarkdown();
        const second = markdownEditor(first).getMarkdown();

        expect(first).toContain('<sup>text</sup>');
        expect(second).toBe(first);
    });
});

describe('callout / alert node', () => {
    it('a `> [!NOTE]` blockquote hydrates into a callout node, not a plain blockquote', () => {
        const doc = markdownEditor('> [!NOTE]\ncontent').getJSON();
        const top = doc.content[0];

        expect(top.type).toBe('callout');
        expect(top.attrs.calloutType).toBe('note');
        // The marker line is consumed; the body survives as the callout's content.
        expect(JSON.stringify(top.content)).toContain('content');
    });

    it('recognises all five callout types', () => {
        const cases = {
            'NOTE': 'note',
            'TIP': 'tip',
            'IMPORTANT': 'important',
            'WARNING': 'warning',
            'CAUTION': 'caution',
        };

        for (const [marker, type] of Object.entries(cases)) {
            const doc = markdownEditor(`> [!${marker}]\nbody`).getJSON();

            expect(doc.content[0].type).toBe('callout');
            expect(doc.content[0].attrs.calloutType).toBe(type);
        }
    });

    it('re-emits the exact `> [!TYPE]` convention and is byte-stable across a round trip (markdown format)', () => {
        const first = markdownEditor('> [!WARNING]\nbe careful').getMarkdown();
        const second = markdownEditor(first).getMarkdown();

        expect(first).toContain('> [!WARNING]');
        expect(first).toContain('> be careful');
        expect(second).toBe(first);
    });

    it('a plain blockquote with no [!TYPE] marker stays an ordinary blockquote (regression guard)', () => {
        const doc = markdownEditor('> just a quote\n> second line').getJSON();

        expect(doc.content[0].type).toBe('blockquote');

        // And it still round-trips unchanged.
        const first = markdownEditor('> just a quote\n> second line').getMarkdown();
        const second = markdownEditor(first).getMarkdown();
        expect(first).toContain('> just a quote');
        expect(second).toBe(first);
    });

    it('a `[!NOTE] text` marker sharing its line is NOT a callout (matches GitHub)', () => {
        const doc = markdownEditor('> [!NOTE] inline text after marker').getJSON();

        expect(doc.content[0].type).toBe('blockquote');
    });

    it('presents over <blockquote> via data-callout-type and round-trips in html format', () => {
        const source = '<blockquote data-callout-type="warning"><p>hi</p></blockquote>';
        const first = htmlEditor(source).getHTML();
        const second = htmlEditor(first).getHTML();

        expect(first).toContain('data-callout-type="warning"');
        expect(first).toContain('<blockquote');
        expect(second).toBe(first);
    });

    it('the Callout entry exists in both slash-menu formats', () => {
        for (const format of ['html', 'markdown']) {
            const titles = buildSlashItems(format, () => {}, () => {}).map((item) => item.title);

            expect(titles).toContain('Callout');
        }
    });
});

describe('slash menu — no merge/split entry in either format', () => {
    it('the table/image/task-list entries exist in both formats', () => {
        for (const format of ['html', 'markdown']) {
            const titles = buildSlashItems(format, () => {}, () => {}).map((item) => item.title);

            expect(titles).toContain('Table');
            expect(titles).toContain('Image');
            expect(titles).toContain('Task list');
        }
    });

    it('no slash entry inserts a merge or split command — that is a post-insertion toolbar-only operation', () => {
        for (const format of ['html', 'markdown']) {
            const titles = buildSlashItems(format, () => {}, () => {}).map((item) => item.title.toLowerCase());

            expect(titles.some((title) => title.includes('merge') || title.includes('split'))).toBe(false);
        }
    });
});

describe('wysiwyg:text-changed CustomEvent', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    function createAlpineStub() {
        const factories = {};

        return {
            data(name, factory) {
                factories[name] = factory;
            },
            factory(name) {
                return factories[name];
            },
        };
    }

    function mountWysiwyg(format) {
        const el = document.createElement('div');
        const textarea = document.createElement('textarea');
        const editorMount = document.createElement('div');
        el.appendChild(textarea);
        el.appendChild(editorMount);
        document.body.appendChild(el);

        const Alpine = createAlpineStub();
        registerWysiwyg(Alpine);

        const component = Alpine.factory('wysiwyg')({ format });
        component.$el = el;
        component.$refs = { textarea, editor: editorMount };
        component.init();

        return { el, component };
    }

    it.each(['markdown', 'html'])('fires with the rendered text when the %s-format editor changes', (format) => {
        const { el, component } = mountWysiwyg(format);
        const handler = vi.fn();
        el.addEventListener('wysiwyg:text-changed', handler);

        component.cmd('insertContent', 'hello world');

        expect(handler).toHaveBeenCalledTimes(1);
        expect(handler.mock.calls[0][0].detail.text).toBe('hello world');

        component.destroy();
    });

    it('fires again, with the updated text, on a second edit', () => {
        const { el, component } = mountWysiwyg('markdown');
        const handler = vi.fn();
        el.addEventListener('wysiwyg:text-changed', handler);

        component.cmd('insertContent', 'hello');
        component.cmd('insertContent', ' world');

        expect(handler).toHaveBeenCalledTimes(2);
        expect(handler.mock.calls[1][0].detail.text).toBe('hello world');

        component.destroy();
    });
});

describe('block alignment — HTML-mode only', () => {
    const extensionNames = (format) =>
        new Editor({ extensions: buildExtensions(format), content: '<p></p>' }).extensionManager.extensions.map(
            (extension) => extension.name
        );

    it('setTextAlign writes a class, never an inline style', () => {
        const editor = htmlEditor('<p>text</p>');
        editor.commands.setTextAlign({ align: 'center' });
        const html = editor.getHTML();

        expect(html).toContain('class="rt-align-center"');
        expect(html).not.toContain('style');
    });

    it('left writes no class, so existing content needs no migration', () => {
        const editor = htmlEditor('<p class="rt-align-center">text</p>');
        editor.commands.setTextAlign({ align: 'left' });

        expect(editor.getHTML()).not.toContain('rt-align');
    });

    it('an alignment class round-trips through getHTML unchanged', () => {
        const first = htmlEditor('<h2 class="rt-align-right">text</h2>').getHTML();
        const second = htmlEditor(first).getHTML();

        expect(first).toContain('rt-align-right');
        expect(second).toBe(first);
    });

    it('drops an alignment name outside the registry', () => {
        expect(htmlEditor('<p class="rt-align-sideways">text</p>').getHTML()).not.toContain('rt-align');
    });

    it('neither decorative extension is registered in markdown format', () => {
        expect(extensionNames('html')).toEqual(expect.arrayContaining(['textAlign', 'textColor']));
        expect(extensionNames('markdown')).not.toContain('textAlign');
        expect(extensionNames('markdown')).not.toContain('textColor');
    });
});

describe('named text colour — HTML-mode only', () => {
    it('setTextColor writes a span class, never an inline style', () => {
        const editor = htmlEditor('<p>text</p>');
        editor.commands.selectAll();
        editor.commands.setTextColor({ color: 'blue' });
        const html = editor.getHTML();

        expect(html).toContain('class="rt-color-blue"');
        expect(html).not.toContain('style');
    });

    it('a colour mark round-trips through getHTML unchanged', () => {
        const first = htmlEditor('<p><span class="rt-color-amber">text</span></p>').getHTML();
        const second = htmlEditor(first).getHTML();

        expect(first).toContain('rt-color-amber');
        expect(second).toBe(first);
    });

    it('parseHTML drops a colour outside the registry', () => {
        const html = htmlEditor('<p><span class="rt-color-chartreuse">text</span></p>').getHTML();

        expect(html).not.toContain('rt-color');
        expect(html).toContain('text');
    });

    it('unsetTextColor removes the mark', () => {
        const editor = htmlEditor('<p><span class="rt-color-red">text</span></p>');
        editor.commands.selectAll();
        editor.commands.unsetTextColor();

        expect(editor.getHTML()).not.toContain('rt-color');
    });
});

describe('slash menu — decoration is gated by format, like the toolbar', () => {
    const titles = (format) => buildSlashItems(format, () => {}, () => {}).map((item) => item.title);

    it('markdown format offers no align or colour entry', () => {
        const markdown = titles('markdown').map((title) => title.toLowerCase());

        expect(markdown.some((title) => title.startsWith('align'))).toBe(false);
        expect(markdown.some((title) => title.startsWith('colour'))).toBe(false);
    });

    it('html format offers one entry per registry value', () => {
        const html = titles('html');

        for (const align of registryList('ALIGNMENTS')) {
            expect(html).toContain(`Align ${align}`);
        }
        for (const color of registryList('TEXT_COLORS')) {
            expect(html).toContain(`Colour ${color}`);
        }
    });
});

describe('registry parity — the JS literals match the PHP constants', () => {
    it.each(['ALIGNMENTS', 'TEXT_COLORS'])('%s agrees with RichTextFields.php', (constant) => {
        expect(sourceList(readSource('resources/js/wysiwyg.js'), constant)).toEqual(registryList(constant));
    });
});

describe('smart punctuation', () => {
    /**
     * Drive the real input-rule path. `insertContent()` does not fire input
     * rules — it inserts a node — so a test written with it passes while the
     * editor does nothing. This mirrors what ProseMirror does on a keystroke.
     */
    function typeText(editor, text) {
        for (const ch of text) {
            const { from, to } = editor.state.selection;
            const handled = editor.view.someProp('handleTextInput', (f) => f(editor.view, from, to, ch));

            if (!handled) {
                editor.view.dispatch(editor.state.tr.insertText(ch, from, to));
            }
        }

        return editor.getText();
    }

    function typedInto(text, format = 'html') {
        const editor = new Editor({ extensions: buildExtensions(format), content: '<p></p>' });
        editor.commands.focus();

        return typeText(editor, text);
    }

    // CommonMark's convention, the same one `tests/Fixtures/punctuation.json` defines.
    // Typography's own emDash rule fires on two hyphens and would disagree.
    it('turns two hyphens into an en dash and three into an em dash', () => {
        expect(typedInto('1914--1918')).toBe('1914\u20131918');
        expect(typedInto('a --- b')).toBe('a \u2014 b');
    });

    it('leaves a single hyphen alone', () => {
        expect(typedInto('mother-in-law')).toBe('mother-in-law');
    });

    it('turns three dots into an ellipsis', () => {
        expect(typedInto('wait...')).toBe('wait\u2026');
    });

    it('curls quotes and apostrophes', () => {
        expect(typedInto('he said "hi"')).toBe('he said \u201chi\u201d');
        expect(typedInto("don't")).toBe('don\u2019t');
    });

    // The rules a novel does not want. Typography ships 22; we enable six.
    it.each([
        ['an arrow', 'x -> y', 'x -> y'],
        ['a copyright sign', '(c) 2026', '(c) 2026'],
        ['a fraction', '1/2 of it', '1/2 of it'],
        ['a not-equal sign', 'a != b', 'a != b'],
        ['guillemets', 'a << b', 'a << b'],
    ])('does not produce %s', (_label, typed, expected) => {
        expect(typedInto(typed)).toBe(expected);
    });

    it('applies to markdown fields too, so scene prose matches the exported book', () => {
        expect(typedInto('a --- b', 'markdown')).toBe('a \u2014 b');
    });

    /**
     * Input rules fire on keystrokes only. Imported text is normalized on the way
     * in by `App\Services\Import\ContentSanitizer` instead, so it never reaches
     * the editor un-canonical.
     */
    it('leaves loaded content untouched', () => {
        const editor = new Editor({ extensions: buildExtensions('html'), content: '<p>a -- b and c... d</p>' });

        expect(editor.getText()).toBe('a -- b and c... d');
    });

    it('keeps a real em dash that arrived with imported content', () => {
        const editor = new Editor({ extensions: buildExtensions('html'), content: '<p>a \u2014 b</p>' });

        expect(editor.getText()).toBe('a \u2014 b');
    });
});
