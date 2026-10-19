import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ASK_EVENT } from '../navigation-guard.js';
import { DIALOG_NAME, registerEditorNewScene } from './new-scene.js';

const CONFIG = {
    url: '/books/3/scenes/quick',
    chapterId: 9,
    nameSelector: '#editor-new-scene-name',
    invalidMessage: 'invalid',
    failureMessage: 'failed',
};

function mount(store) {
    let factory = null;
    registerEditorNewScene({ store: () => store, data: (name, make) => { factory = make; } });

    const component = factory(CONFIG);
    component.visit = vi.fn();

    return component;
}

function cleanStore() {
    return { flushAll: vi.fn().mockResolvedValue(), isDirty: () => false };
}

describe('editor new scene dialog', () => {
    let events;
    const record = (event) => events.push(event);

    beforeEach(() => {
        events = [];
        window.addEventListener(ASK_EVENT, record);
        window.addEventListener('close-modal', record);
        window.axios = { post: vi.fn().mockResolvedValue({ data: { url: '/scenes/42/write' } }) };
    });

    afterEach(() => {
        window.removeEventListener(ASK_EVENT, record);
        window.removeEventListener('close-modal', record);
        delete window.axios;
    });

    it('defaults to the current chapter', () => {
        expect(mount(cleanStore()).chapterId).toBe('9');
    });

    it('posts the chapter and the title, then opens the new scene after the flush', async () => {
        const store = cleanStore();
        const dialog = mount(store);
        dialog.name = 'The Fountain';

        await dialog.submit();

        expect(window.axios.post).toHaveBeenCalledWith('/books/3/scenes/quick', { chapter_id: '9', name: 'The Fountain' });
        expect(store.flushAll).toHaveBeenCalled();
        expect(dialog.visit).toHaveBeenCalledWith('/scenes/42/write');
    });

    it('shows a 422 error inside the dialog and keeps it open', async () => {
        window.axios.post.mockRejectedValue({ response: { status: 422, data: { message: 'The title field is required.' } } });
        const dialog = mount(cleanStore());

        await dialog.submit();

        expect(dialog.error).toBe('The title field is required.');
        expect(dialog.busy).toBe(false);
        expect(dialog.visit).not.toHaveBeenCalled();
        expect(events.some((event) => event.type === 'close-modal' && event.detail === DIALOG_NAME)).toBe(false);
    });

    it('shows the failure message on a network error', async () => {
        window.axios.post.mockRejectedValue(new Error('offline'));
        const dialog = mount(cleanStore());

        await dialog.submit();

        expect(dialog.error).toBe('failed');
    });

    it('asks the leave guard and does not navigate when the flush fails', async () => {
        const store = { flushAll: vi.fn().mockRejectedValue(new Error('offline')), isDirty: () => true };
        const dialog = mount(store);
        dialog.name = 'The Fountain';

        await dialog.submit();

        expect(dialog.visit).not.toHaveBeenCalled();

        const ask = events.find((event) => event.type === ASK_EVENT);
        expect(ask.detail).toEqual({ href: '/scenes/42/write' });
    });
});
