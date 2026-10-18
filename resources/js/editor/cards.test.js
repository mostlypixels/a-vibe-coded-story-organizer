import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fillUrl, registerEditorCards } from './cards.js';

const SAVE_URL = '/scenes/3/editor-cards';

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

function mount(existing = '') {
    document.body.innerHTML = `
        <input data-query>
        <div data-cards>${existing}</div>
        <template data-template>${cardMarkup('', '')}</template>`;

    let factory = null;
    registerEditorCards({ data: (name, make) => { factory = make; } });

    const component = factory({
        type: 'codex',
        candidatesUrl: '/projects/1/link-targets',
        cardUrl: '/codex/__ID__/card?scene=3',
        viewUrl: '/codex/__ID__',
        saveUrl: SAVE_URL,
        closeLabel: 'Close :name',
        viewLabel: 'Open :name in a new tab',
        debounceMs: 0,
        searchFailureMessage: 'search failed',
        loadFailureMessage: 'load failed',
        saveFailureMessage: 'save failed',
    });

    component.$refs = {
        query: document.querySelector('[data-query]'),
        cards: document.querySelector('[data-cards]'),
        template: document.querySelector('[data-template]'),
    };

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
});
