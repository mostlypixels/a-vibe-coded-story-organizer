import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { OPEN_EVENT, registerQuickCodexEntry, renderConfirmation } from './quick-codex-entry';

function createAlpineStub(store) {
    const factories = {};

    return {
        store: () => store,
        data(name, factory) {
            factories[name] = factory;
        },
        factory(name) {
            return factories[name];
        },
    };
}

const CONFIG = {
    url: '/scenes/7/codex-entries',
    autosaveKey: 'scene:7:contents',
    editorSelector: '[data-editor]',
    nameSelector: '#quick-codex-entry-name',
    listSelector: '[data-codex-references-list]',
    confirmationSelector: '[data-codex-entry-created]',
    entryUrlTemplate: '/codex/__ID__',
    defaultType: 'character',
    successMessage: 'Added :name to the codex.',
    invalidMessage: 'That entry could not be created.',
    failureMessage: 'The entry was not created.',
};

const CREATED = {
    id: 12,
    name: 'Melusine',
    type: 'character',
    type_label: 'Character',
    url: '/codex/12',
};

/** The sidebar as the scene page renders it, plus the editor the caret returns to. */
function buildPage() {
    document.body.innerHTML = `
        <div data-editor contenteditable="true"><p>Raymondin waits.</p></div>
        <input id="quick-codex-entry-name" type="text">
        <p data-codex-entry-created></p>
        <div data-codex-references-list><ul><li>Raymondin</li></ul></div>
    `;

    return {
        editor: document.querySelector('[data-editor]'),
        nameInput: document.querySelector('#quick-codex-entry-name'),
        status: document.querySelector('[data-codex-entry-created]'),
        list: document.querySelector('[data-codex-references-list]'),
    };
}

/** Select `text` inside the editor, the way a writer highlights a name. */
function selectInEditor(editor, text) {
    const node = editor.querySelector('p').firstChild;
    const start = node.textContent.indexOf(text);

    const range = document.createRange();
    range.setStart(node, start);
    range.setEnd(node, start + text.length);

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}

describe('renderConfirmation', () => {
    it('names the entry as a link inside the translated line', () => {
        const node = document.createElement('p');

        renderConfirmation(node, 'Added :name to the codex.', CREATED);

        expect(node.textContent).toBe('Added Melusine to the codex.');
        expect(node.querySelector('a').getAttribute('href')).toBe('/codex/12');
    });
});

describe('quickCodexEntry', () => {
    let Alpine;
    let store;
    let page;

    beforeEach(() => {
        page = buildPage();
        store = { flush: vi.fn().mockResolvedValue(undefined) };
        Alpine = createAlpineStub(store);
        registerQuickCodexEntry(Alpine);
    });

    afterEach(() => {
        vi.restoreAllMocks();
        delete window.axios;
        document.body.innerHTML = '';
    });

    function mount() {
        const component = Alpine.factory('quickCodexEntry')(CONFIG);
        component.init();

        return component;
    }

    it('prefills the name from the editor selection, trimmed', () => {
        const component = mount();
        selectInEditor(page.editor, 'Raymondin ');

        window.dispatchEvent(new CustomEvent(OPEN_EVENT));

        expect(component.name).toBe('Raymondin');
    });

    it('prefills nothing when the selection is outside the editor', () => {
        const component = mount();
        window.getSelection().removeAllRanges();

        window.dispatchEvent(new CustomEvent(OPEN_EVENT));

        expect(component.name).toBe('');
    });

    it('flushes the pending prose autosave before posting, without the matcher', async () => {
        const order = [];
        store.flush = vi.fn(() => {
            order.push('flush');

            return Promise.resolve();
        });
        window.axios = {
            post: vi.fn(() => {
                order.push('post');

                return Promise.resolve({ data: { entry: CREATED, referenced_entries_html: '<ul></ul>' } });
            }),
        };

        const component = mount();
        component.name = 'Melusine';

        await component.submit();

        expect(order).toEqual(['flush', 'post']);
        expect(store.flush).toHaveBeenCalledWith('scene:7:contents', { runMatcher: false });
    });

    it('replaces the whole reference list, so an entry the resync dropped disappears', async () => {
        window.axios = {
            post: vi.fn().mockResolvedValue({
                data: { entry: CREATED, referenced_entries_html: '<ul><li>Melusine</li></ul>' },
            }),
        };

        const component = mount();
        component.name = 'Melusine';

        await component.submit();

        expect(page.list.innerHTML).toBe('<ul><li>Melusine</li></ul>');
        expect(page.list.textContent).not.toContain('Raymondin');
    });

    it('confirms the creation even when the returned list does not hold the new entry', async () => {
        window.axios = {
            post: vi.fn().mockResolvedValue({
                data: { entry: CREATED, referenced_entries_html: '<ul><li>Raymondin</li></ul>' },
            }),
        };

        const component = mount();
        component.name = 'Melusine';

        await component.submit();

        expect(page.status.textContent).toBe('Added Melusine to the codex.');
        expect(page.list.textContent).not.toContain('Melusine');
    });

    it('keeps the dialog open with the typed name and offers the existing entry on a 422', async () => {
        window.axios = {
            post: vi.fn().mockRejectedValue({
                response: {
                    status: 422,
                    data: { message: 'An entry named Melusine already exists.', existing_entry_id: 9 },
                },
            }),
        };

        const component = mount();
        component.name = 'Melusine';
        const closed = vi.fn();
        window.addEventListener('close-modal', closed);

        await component.submit();

        expect(component.name).toBe('Melusine');
        expect(component.error).toBe('An entry named Melusine already exists.');
        expect(component.existingUrl).toBe('/codex/9');
        expect(component.busy).toBe(false);
        expect(closed).not.toHaveBeenCalled();
        expect(page.status.textContent).toBe('');

        window.removeEventListener('close-modal', closed);
    });

    it('keeps the dialog open with a generic message when the request fails', async () => {
        window.axios = { post: vi.fn().mockRejectedValue(new Error('Network Error')) };

        const component = mount();
        component.name = 'Melusine';
        const closed = vi.fn();
        window.addEventListener('close-modal', closed);

        await component.submit();

        expect(component.name).toBe('Melusine');
        expect(component.error).toBe(CONFIG.failureMessage);
        expect(closed).not.toHaveBeenCalled();
        expect(page.list.textContent).toContain('Raymondin');

        window.removeEventListener('close-modal', closed);
    });

    it('closes and returns the caret to the editor on success', async () => {
        window.axios = {
            post: vi.fn().mockResolvedValue({
                data: { entry: CREATED, referenced_entries_html: '<ul></ul>' },
            }),
        };

        const component = mount();
        selectInEditor(page.editor, 'Raymondin');
        window.dispatchEvent(new CustomEvent(OPEN_EVENT));

        const closed = vi.fn();
        window.addEventListener('close-modal', closed);

        await component.submit();

        expect(closed).toHaveBeenCalled();
        expect(document.activeElement).toBe(page.editor);

        window.removeEventListener('close-modal', closed);
    });

    it('returns the caret to the editor when the writer cancels', () => {
        const component = mount();
        selectInEditor(page.editor, 'Raymondin');
        window.dispatchEvent(new CustomEvent(OPEN_EVENT));

        component.close();

        expect(document.activeElement).toBe(page.editor);
    });

    it('never touches the editor when the dialog was not opened', () => {
        const component = mount();
        page.status.textContent = 'untouched';

        component.restoreEditorFocus();

        expect(document.activeElement).not.toBe(page.editor);
    });

    it('gives the name field the keyboard after the modal has taken its own focus', () => {
        const component = mount();

        window.dispatchEvent(new CustomEvent(OPEN_EVENT));
        component.claimFocus();

        expect(document.activeElement).toBe(page.nameInput);
    });

    it('sends the caret back to the prose when the dialog closed before that focus landed', () => {
        const component = mount();
        selectInEditor(page.editor, 'Raymondin');

        window.dispatchEvent(new CustomEvent(OPEN_EVENT));
        component.close();
        component.claimFocus();

        expect(document.activeElement).toBe(page.editor);
    });

    it('takes a name handed in by the caller over the editor selection', () => {
        const component = mount();
        selectInEditor(page.editor, 'Raymondin');

        window.dispatchEvent(new CustomEvent(OPEN_EVENT, { detail: { name: '  Melusine ' } }));

        expect(component.name).toBe('Melusine');
    });
});
