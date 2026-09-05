import { describe, expect, it } from 'vitest';
import { Editor } from '@tiptap/core';
import { buildExtensions } from './wysiwyg.js';
import { normalizePunctuation } from './punctuation.js';
import cases from '../../tests/Fixtures/punctuation.json';

/**
 * The fixture is the shared contract between the PHP importer, the editor's
 * input rules, and the paste handler. A case may not be skipped here: if a rule
 * cannot express one, that is a finding for the resolution log.
 */

/**
 * Drive the real input-rule path. `insertContent()` inserts a node and fires no
 * input rule, so a test written with it passes while the editor does nothing.
 */
function typedInto(text, format = 'html') {
    const editor = new Editor({ extensions: buildExtensions(format), content: '<p></p>' });
    editor.commands.focus();

    for (const ch of text) {
        const { from, to } = editor.state.selection;
        const handled = editor.view.someProp('handleTextInput', (f) => f(editor.view, from, to, ch));

        if (!handled) {
            editor.view.dispatch(editor.state.tr.insertText(ch, from, to));
        }
    }

    return editor.getText();
}

describe('typing matches the punctuation fixture', () => {
    it.each(cases.map((one) => [one.note ? `${one.input} — ${one.note}` : one.input, one]))(
        '%s',
        (_label, one) => {
            expect(typedInto(one.input)).toBe(one.expected);
        },
    );

    it.each(cases.map((one) => [one.input, one]))('%s in a markdown field', (_label, one) => {
        expect(typedInto(one.input, 'markdown')).toBe(one.expected);
    });
});

/**
 * Drive the real paste path. `view.pasteText()` runs `transformPastedText` and
 * inserts the result, so a broken plugin registration fails here.
 */
function pastedInto(text, format = 'html', content = '<p></p>') {
    const editor = new Editor({ extensions: buildExtensions(format), content });
    editor.commands.focus('end');
    editor.view.pasteText(text, new Event('paste'));

    return editor;
}

describe('pasting matches the punctuation fixture', () => {
    it.each(cases.map((one) => [one.note ? `${one.input} — ${one.note}` : one.input, one]))(
        '%s',
        (_label, one) => {
            expect(pastedInto(one.input).getText()).toBe(one.expected);
        },
    );

    it.each(cases.map((one) => [one.input, one]))('%s in a markdown field', (_label, one) => {
        expect(pastedInto(one.input, 'markdown').getText()).toBe(one.expected);
    });

    it('normalizes pasted text only once', () => {
        const once = normalizePunctuation('"Hello," she said -- and then...');

        expect(normalizePunctuation(once)).toBe(once);
    });

    it('leaves a paste into a code block alone', () => {
        expect(pastedInto('a -- b', 'html', '<pre><code></code></pre>').getText().trim()).toBe(
            'a -- b',
        );
    });

    it('leaves a paste under a code mark alone', () => {
        const editor = new Editor({ extensions: buildExtensions('html'), content: '<p></p>' });
        editor.commands.focus();
        editor.commands.toggleCode();
        editor.view.pasteText('a -- b', new Event('paste'));

        expect(editor.getText()).toBe('a -- b');
    });
});
