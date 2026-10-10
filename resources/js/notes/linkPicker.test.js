import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerLinkPicker } from './linkPicker';

function makeComponent(config) {
    let factory;

    registerLinkPicker({
        data(name, fn) {
            factory = fn;
        },
    });

    return factory(config);
}

const NOTE_SIDE = {
    candidatesUrl: '/notes/3/link-candidates',
    storeUrl: '/notes/3/links',
    type: 'scene',
    dialog: 'note-link',
    refreshSelector: '[data-note-links]',
    debounceMs: 200,
    failureMessage: 'The link was not saved.',
    searchFailureMessage: 'The search failed.',
};

const ENTITY_SIDE = {
    candidatesUrl: '/projects/1/notes/candidates',
    storeUrl: '/notes/__NOTE__/links',
    linkType: 'scene',
    linkId: 12,
    dialog: 'scene-note-link',
    refreshSelector: '[data-note-links]',
    failureMessage: 'The link was not saved.',
};

const FRESH_PAGE = '<html><body><div data-note-links><a>The fountain</a></div></body></html>';

describe('linkPicker', () => {
    beforeEach(() => {
        document.body.innerHTML = '<div data-note-links><p>No links</p></div>';
        window.axios = { get: vi.fn(), post: vi.fn(), delete: vi.fn() };
    });

    afterEach(() => {
        vi.useRealTimers();
        delete window.axios;
    });

    it('debounces the search and sends the type on the note side', async () => {
        vi.useFakeTimers();
        window.axios.get.mockResolvedValue({ data: [{ type: 'scene', id: 5, label: 'The fountain' }] });
        const picker = makeComponent(NOTE_SIDE);

        picker.query = 'fou';
        picker.queueSearch();
        picker.queueSearch();
        expect(window.axios.get).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(200);

        expect(window.axios.get).toHaveBeenCalledTimes(1);
        expect(window.axios.get).toHaveBeenCalledWith('/notes/3/link-candidates', { params: { q: 'fou', type: 'scene' } });
        expect(picker.results).toHaveLength(1);
        expect(picker.label(picker.results[0])).toBe('The fountain');
    });

    it('ignores a slow response that a newer search replaced', async () => {
        let resolveOld;
        window.axios.get
            .mockReturnValueOnce(new Promise((resolve) => { resolveOld = resolve; }))
            .mockResolvedValueOnce({ data: [{ id: 2, title: 'New' }] });
        const picker = makeComponent(ENTITY_SIDE);

        const old = picker.search();
        await picker.search();
        resolveOld({ data: [{ id: 1, title: 'Old' }] });
        await old;

        expect(picker.results).toEqual([{ id: 2, title: 'New' }]);
    });

    it('posts the picked entity and swaps in the fresh list', async () => {
        window.axios.post.mockResolvedValue({ status: 204 });
        window.axios.get.mockResolvedValue({ data: FRESH_PAGE });
        const closed = vi.fn();
        window.addEventListener('close-modal', closed);
        const picker = makeComponent(NOTE_SIDE);

        await picker.pick({ type: 'chapter', id: 7, label: 'Chapter one' });

        expect(window.axios.post).toHaveBeenCalledWith('/notes/3/links', { type: 'chapter', id: 7 });
        expect(closed).toHaveBeenCalled();
        expect(document.querySelector('[data-note-links]').textContent).toBe('The fountain');
        expect(picker.busy).toBe(false);
        window.removeEventListener('close-modal', closed);
    });

    it('posts the fixed entity to the picked note on the entity side', async () => {
        window.axios.post.mockResolvedValue({ status: 204 });
        window.axios.get.mockResolvedValue({ data: FRESH_PAGE });
        const picker = makeComponent(ENTITY_SIDE);

        await picker.pick({ id: 9, title: 'Research' });

        expect(window.axios.post).toHaveBeenCalledWith('/notes/9/links', { type: 'scene', id: 12 });
    });

    it('does not send the type when it searches notes', async () => {
        window.axios.get.mockResolvedValue({ data: [] });
        const picker = makeComponent(ENTITY_SIDE);

        picker.query = 'res';
        await picker.search();

        expect(window.axios.get).toHaveBeenCalledWith('/projects/1/notes/candidates', { params: { q: 'res' } });
    });

    it('unlinks through a DELETE and refreshes the list', async () => {
        window.axios.delete.mockResolvedValue({ status: 204 });
        window.axios.get.mockResolvedValue({ data: FRESH_PAGE });
        const picker = makeComponent(NOTE_SIDE);

        await picker.unlink('/notes/3/links/scene/5');

        expect(window.axios.delete).toHaveBeenCalledWith('/notes/3/links/scene/5');
        expect(document.querySelector('[data-note-links]').textContent).toBe('The fountain');
    });

    it('shows the server message on a validation error and keeps the list', async () => {
        window.axios.post.mockRejectedValue({ response: { status: 422, data: { message: 'The selected id is invalid.' } } });
        const picker = makeComponent(NOTE_SIDE);

        await picker.pick({ type: 'scene', id: 99, label: 'Elsewhere' });

        expect(picker.error).toBe('The selected id is invalid.');
        expect(picker.busy).toBe(false);
        expect(window.axios.get).not.toHaveBeenCalled();
        expect(document.querySelector('[data-note-links]').textContent).toBe('No links');
    });

    it('shows the failure message on a network error', async () => {
        window.axios.get.mockRejectedValue(new Error('offline'));
        const picker = makeComponent(NOTE_SIDE);

        await picker.search();

        expect(picker.error).toBe('The search failed.');
        expect(picker.results).toEqual([]);
        expect(picker.searching).toBe(false);
    });
});
