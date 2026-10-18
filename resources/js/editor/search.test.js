import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerEditorSceneSearch, sceneUrl } from './search.js';

const TEMPLATE = 'http://app.test/scenes/__SCENE__/write';

function mount(overrides = {}) {
    let factory = null;
    registerEditorSceneSearch({ data: (name, make) => { factory = make; } });

    return factory({
        candidatesUrl: '/projects/1/link-targets',
        sceneUrl: TEMPLATE,
        debounceMs: 0,
        failureMessage: 'failed',
        ...overrides,
    });
}

describe('editor scene search', () => {
    beforeEach(() => {
        window.axios = { get: vi.fn().mockResolvedValue({ data: [{ type: 'scene', id: 7, label: 'Storm' }] }) };
    });

    afterEach(() => {
        delete window.axios;
    });

    it('maps a result to the Write URL', () => {
        expect(sceneUrl(TEMPLATE, { id: 7 })).toBe('http://app.test/scenes/7/write');
        expect(mount().urlFor({ id: 12 })).toBe('http://app.test/scenes/12/write');
    });

    it('asks for scenes only', async () => {
        const search = mount();
        search.query = 'sto';

        await search.search();

        expect(window.axios.get).toHaveBeenCalledWith('/projects/1/link-targets', { params: { q: 'sto', type: 'scene' } });
        expect(search.results).toHaveLength(1);
    });

    it('shows the recent list for an empty query', () => {
        const search = mount();

        expect(search.showingRecent()).toBe(true);

        search.query = '  ';
        expect(search.showingRecent()).toBe(true);

        search.query = 'a';
        expect(search.showingRecent()).toBe(false);
    });

    it('returns to the recent list when the query is cleared', async () => {
        const search = mount();
        search.query = 'sto';
        await search.search();

        search.query = '';
        search.onInput();

        expect(search.showingRecent()).toBe(true);
        expect(search.results).toEqual([]);
    });

    it('ignores an answer that arrives after the query was cleared', async () => {
        let answer;
        window.axios.get = vi.fn(() => new Promise((resolve) => { answer = resolve; }));
        const search = mount();
        search.query = 'sto';
        const pending = search.search();

        search.query = '';
        search.onInput();
        answer({ data: [{ type: 'scene', id: 7, label: 'Storm' }] });
        await pending;

        expect(search.results).toEqual([]);
    });

    it('Escape clears the query and the results', async () => {
        const search = mount();
        search.query = 'sto';
        await search.search();

        search.onEscape();

        expect(search.query).toBe('');
        expect(search.results).toEqual([]);
    });

    it('Enter clicks the highlighted link', () => {
        const search = mount();
        const list = document.createElement('ul');
        list.innerHTML = '<li><a href="#a">A</a></li><li><a href="#b">B</a></li>';
        const clicked = [];
        list.querySelectorAll('a').forEach((a, i) => a.addEventListener('click', () => clicked.push(i)));
        search.$refs = { results: list };

        search.results = [{ id: 1 }, { id: 2 }];
        search.move(1);
        search.openHighlighted();

        expect(clicked).toEqual([1]);
    });
});
