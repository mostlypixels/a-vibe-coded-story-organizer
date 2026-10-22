import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fillUrl, registerEditorCards } from './cards.js';

const SAVE_URL = '/scenes/3/editor-cards';
const REFRESH_URL = '/scenes/3/editor-cards/codex';

const mounted = [];

function cardMarkup(id, title, state = 'expanded') {
    return `
        <article data-editor-card data-card-type="codex" data-card-id="${id}" data-state="${state}">
            <header>
                <button type="button" aria-expanded="${state === 'expanded'}" aria-controls="editor-card-codex-${id}" data-card-action="toggle">
                    <span data-card-title>${title}</span>
                </button>
                <a href="#" title="Open" data-card-view><span class="sr-only">Open</span></a>
                <button type="button" title="Close" data-card-action="close"><span class="sr-only">Close</span></button>
            </header>
            <div id="editor-card-codex-${id}" data-card-body ${state === 'expanded' ? '' : 'hidden'}></div>
        </article>`;
}

function mount(existing = '', { open = true } = {}) {
    document.body.innerHTML = `
        <details data-details ${open ? 'open' : ''}>
            <summary>Codex</summary>
            <input data-query>
            <div data-cards>${existing}</div>
            <template data-template>${cardMarkup('', '')}</template>
        </details>`;

    let factory = null;
    registerEditorCards({ data: (name, make) => { factory = make; } });

    const component = factory({
        type: 'codex',
        candidatesUrl: '/projects/1/link-targets',
        cardUrl: '/codex/__ID__/card?scene=3',
        viewUrl: '/codex/__ID__',
        saveUrl: SAVE_URL,
        refreshUrl: REFRESH_URL,
        closeLabel: 'Close :name',
        viewLabel: 'Open :name in a new tab',
        debounceMs: 0,
        searchFailureMessage: 'search failed',
        loadFailureMessage: 'load failed',
        saveFailureMessage: 'save failed',
        refreshFailureMessage: 'refresh failed',
    });

    component.$refs = {
        query: document.querySelector('[data-query]'),
        cards: document.querySelector('[data-cards]'),
        template: document.querySelector('[data-template]'),
        details: document.querySelector('[data-details]'),
    };
    component.init();
    mounted.push(component);

    return component;
}

const cards = () => [...document.querySelectorAll('[data-cards] [data-editor-card]')];
const click = (component, element) => component.onCardClick({ target: element });

describe('editor cards', () => {
    beforeEach(() => {
        window.axios = {
            get: vi.fn().mockResolvedValue({ data: '<p>Body of the card</p>' }),
            put: vi.fn().mockResolvedValue({ status: 204 }),
        };
    });

    afterEach(() => {
        mounted.splice(0).forEach((component) => component.destroy());
        delete window.axios;
        document.body.innerHTML = '';
    });

    it('fills an id into a URL template', () => {
        expect(fillUrl('/codex/__ID__/card?scene=3', 12)).toBe('/codex/12/card?scene=3');
    });

    it('searches its own type only', async () => {
        const component = mount();
        component.query = 'jav';

        await component.search();

        expect(window.axios.get).toHaveBeenCalledWith('/projects/1/link-targets', { params: { q: 'jav', type: 'codex' } });
    });

    it('adds a picked card on top, expanded, and saves it', async () => {
        const component = mount(cardMarkup(1, 'Older'));
        component.query = 'jav';

        await component.pick({ type: 'codex', id: 7, label: 'Javert' });

        expect(window.axios.get).toHaveBeenCalledWith('/codex/7/card?scene=3');
        expect(component.query).toBe('');

        const [card] = cards();
        expect(cards()).toHaveLength(2);
        expect(card.dataset.cardId).toBe('7');
        expect(card.dataset.state).toBe('expanded');
        expect(card.querySelector('[data-card-title]').textContent).toBe('Javert');
        expect(card.querySelector('[data-card-body]').innerHTML).toBe('<p>Body of the card</p>');
        expect(card.querySelector('[data-card-body]').id).toBe('editor-card-codex-7');
        expect(card.querySelector('[data-card-view]').getAttribute('href')).toBe('/codex/7');
        expect(card.querySelector('[data-card-action="close"]').title).toBe('Close Javert');
        expect(card.querySelector('[data-card-action="close"] .sr-only').textContent).toBe('Close Javert');
        expect(document.activeElement).toBe(card.querySelector('[data-card-action="toggle"]'));
        expect(window.axios.put).toHaveBeenCalledWith(SAVE_URL, { type: 'codex', id: 7, state: 'expanded' });
    });

    it('does not add a second card for an open one: it expands and focuses it', async () => {
        const component = mount(cardMarkup(7, 'Javert', 'collapsed'));

        await component.pick({ type: 'codex', id: 7, label: 'Javert' });

        expect(cards()).toHaveLength(1);
        expect(window.axios.get).not.toHaveBeenCalled();
        expect(cards()[0].dataset.state).toBe('expanded');
        expect(cards()[0].querySelector('[data-card-body]').hidden).toBe(false);
        expect(document.activeElement).toBe(cards()[0].querySelector('[data-card-action="toggle"]'));
        expect(window.axios.put).toHaveBeenCalledWith(SAVE_URL, { type: 'codex', id: 7, state: 'expanded' });
    });

    it('does not save again when the open card is already expanded', async () => {
        const component = mount(cardMarkup(7, 'Javert'));

        await component.pick({ type: 'codex', id: 7, label: 'Javert' });

        expect(window.axios.put).not.toHaveBeenCalled();
    });

    it('collapses and expands a card, and saves each change', () => {
        const component = mount(cardMarkup(4, 'Cosette'));
        const toggle = () => document.querySelector('[data-card-action="toggle"]');

        click(component, toggle().querySelector('[data-card-title]'));

        expect(cards()[0].dataset.state).toBe('collapsed');
        expect(toggle().getAttribute('aria-expanded')).toBe('false');
        expect(cards()[0].querySelector('[data-card-body]').hidden).toBe(true);
        expect(window.axios.put).toHaveBeenLastCalledWith(SAVE_URL, { type: 'codex', id: 4, state: 'collapsed' });

        click(component, toggle());

        expect(toggle().getAttribute('aria-expanded')).toBe('true');
        expect(window.axios.put).toHaveBeenLastCalledWith(SAVE_URL, { type: 'codex', id: 4, state: 'expanded' });
    });

    it('removes a closed card, saves closed and focuses the search', () => {
        const component = mount(cardMarkup(4, 'Cosette'));

        click(component, document.querySelector('[data-card-action="close"]'));

        expect(cards()).toHaveLength(0);
        expect(document.activeElement).toBe(component.$refs.query);
        expect(window.axios.put).toHaveBeenCalledWith(SAVE_URL, { type: 'codex', id: 4, state: 'closed' });
    });

    it('ignores a click outside the card buttons', () => {
        const component = mount(cardMarkup(4, 'Cosette'));

        click(component, document.querySelector('[data-card-body]'));

        expect(window.axios.put).not.toHaveBeenCalled();
    });

    it('keeps the change on screen and shows an error when the save fails', async () => {
        window.axios.put.mockRejectedValue(new Error('offline'));
        const component = mount(cardMarkup(4, 'Cosette'));

        await component.toggle(cards()[0]);

        expect(cards()[0].dataset.state).toBe('collapsed');
        expect(component.saveError).toBe('save failed');

        window.axios.put.mockResolvedValue({ status: 204 });
        await component.toggle(cards()[0]);

        expect(component.saveError).toBe('');
    });

    it('shows the error in the section when the card fetch fails', async () => {
        window.axios.get.mockRejectedValue(new Error('offline'));
        const component = mount();

        await component.pick({ type: 'codex', id: 7, label: 'Javert' });

        expect(cards()).toHaveLength(0);
        expect(component.loadError).toBe('load failed');
        expect(component.loading).toBe(false);
        expect(window.axios.put).not.toHaveBeenCalled();
    });

    it('picks the highlighted result with Enter', async () => {
        const component = mount();
        component.results = [{ type: 'codex', id: 2, label: 'A' }, { type: 'codex', id: 3, label: 'B' }];
        component.highlighted = 1;

        component.pickHighlighted();
        await vi.waitFor(() => expect(cards()).toHaveLength(1));

        expect(cards()[0].dataset.cardId).toBe('3');
    });

    describe('refresh', () => {
        const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
        const refreshCalls = () => window.axios.get.mock.calls.filter(([url]) => url === REFRESH_URL);

        function deferred() {
            let resolve;
            let reject;
            const promise = new Promise((res, rej) => {
                resolve = res;
                reject = rej;
            });

            return { promise, resolve, reject };
        }

        function setVisibility(state) {
            Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state });
        }

        afterEach(() => {
            delete document.visibilityState;
        });

        it('makes one GET on focus, one on visibilitychange to visible, and only one for both together', async () => {
            const component = mount(cardMarkup(1, 'Javert'));
            const refresh = vi.spyOn(component, 'refresh');
            setVisibility('visible');

            window.dispatchEvent(new Event('focus'));
            await flush();
            expect(refreshCalls()).toHaveLength(1);

            document.dispatchEvent(new Event('visibilitychange'));
            await flush();
            expect(refreshCalls()).toHaveLength(2);

            window.dispatchEvent(new Event('focus'));
            document.dispatchEvent(new Event('visibilitychange'));
            await flush();
            expect(refreshCalls()).toHaveLength(3);
            expect(refresh).toHaveBeenCalledTimes(4);
        });

        it('does nothing on visibilitychange to hidden', async () => {
            mount(cardMarkup(1, 'Javert'));
            setVisibility('hidden');

            document.dispatchEvent(new Event('visibilitychange'));
            await flush();

            expect(window.axios.get).not.toHaveBeenCalled();
        });

        it('makes no GET while the section is closed, and one when it opens', async () => {
            const component = mount(cardMarkup(1, 'Javert'), { open: false });

            window.dispatchEvent(new Event('focus'));
            await component.refresh();
            expect(window.axios.get).not.toHaveBeenCalled();

            component.$refs.details.open = true;
            component.onDetailsToggle();
            await flush();
            expect(refreshCalls()).toHaveLength(1);
        });

        it('makes no GET for the toggle event of a section that loads open', async () => {
            const component = mount(cardMarkup(1, 'Javert'));

            component.onDetailsToggle();
            await flush();

            expect(window.axios.get).not.toHaveBeenCalled();
        });

        it('makes one GET for the refresh button', async () => {
            const component = mount(cardMarkup(1, 'Javert'));

            await component.refresh();

            expect(window.axios.get).toHaveBeenCalledTimes(1);
            expect(window.axios.get).toHaveBeenCalledWith(REFRESH_URL);
        });

        it('shows the busy state during the request', async () => {
            const component = mount(cardMarkup(1, 'Javert'));
            const response = deferred();
            window.axios.get.mockReturnValue(response.promise);

            const running = component.refresh();
            expect(component.refreshing).toBe(true);

            response.resolve({ data: cardMarkup(1, 'Javert') });
            await running;
            expect(component.refreshing).toBe(false);
        });

        it('keeps the same nodes when the response is equal', async () => {
            const component = mount(cardMarkup(1, 'Javert') + cardMarkup(2, 'Cosette', 'collapsed'));
            const [first, second] = cards();
            // A local toggle and back sets the attributes again, in another order.
            component.setState(second, 'expanded');
            component.setState(second, 'collapsed');
            window.axios.get.mockResolvedValue({ data: `\n${cardMarkup(1, 'Javert')}\n\n${cardMarkup(2, 'Cosette', 'collapsed')}\n` });

            await component.refresh();

            expect(cards()[0]).toBe(first);
            expect(cards()[1]).toBe(second);
        });

        it('replaces the cards when the response differs, and puts focus back on the same button', async () => {
            const component = mount(cardMarkup(1, 'Javert') + cardMarkup(2, 'Cosette'));
            cards()[1].querySelector('[data-card-action="close"]').focus();
            window.axios.get.mockResolvedValue({ data: cardMarkup(3, 'Fantine') + cardMarkup(2, 'Cosette', 'collapsed') });

            await component.refresh();

            expect(cards().map((card) => card.dataset.cardId)).toEqual(['3', '2']);
            expect(document.activeElement).toBe(cards()[1].querySelector('[data-card-action="close"]'));
        });

        it('waits for a running save before the GET', async () => {
            const component = mount(cardMarkup(1, 'Javert'));
            const save = deferred();
            window.axios.put.mockReturnValue(save.promise);
            window.axios.get.mockResolvedValue({ data: cardMarkup(1, 'Javert', 'collapsed') });

            component.toggle(cards()[0]);
            const running = component.refresh();
            await flush();
            expect(window.axios.get).not.toHaveBeenCalled();

            save.resolve({ status: 204 });
            await running;
            expect(refreshCalls()).toHaveLength(1);
        });

        it('drops the response when a card changes during the GET', async () => {
            const component = mount(cardMarkup(1, 'Javert'));
            const original = cards()[0];
            const response = deferred();
            window.axios.get.mockReturnValue(response.promise);

            const running = component.refresh();
            await flush();
            component.toggle(original);
            response.resolve({ data: cardMarkup(9, 'Valjean') });
            await running;

            expect(cards()).toEqual([original]);
            expect(original.dataset.state).toBe('collapsed');
        });

        it('keeps the cards and shows an error when the GET fails', async () => {
            const component = mount(cardMarkup(1, 'Javert'));
            const original = cards()[0];
            window.axios.get.mockRejectedValue(new Error('offline'));

            await component.refresh();

            expect(cards()).toEqual([original]);
            expect(component.loadError).toBe('refresh failed');
            expect(component.refreshing).toBe(false);

            window.axios.get.mockResolvedValue({ data: cardMarkup(1, 'Javert') });
            await component.refresh();
            expect(component.loadError).toBe('');
        });

        it('removes its listeners in destroy()', async () => {
            const component = mount(cardMarkup(1, 'Javert'));
            mounted.splice(0);

            component.destroy();
            window.dispatchEvent(new Event('focus'));
            document.dispatchEvent(new Event('visibilitychange'));
            await flush();

            expect(window.axios.get).not.toHaveBeenCalled();
        });
    });
});
