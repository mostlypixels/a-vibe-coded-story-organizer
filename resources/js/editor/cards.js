/**
 * One section of the right panel on the Write page: Codex or Notes.
 *
 * The server renders the cards of the page load. A search pick fetches the card body and fills a copy
 * of the section's card template. Each state change is saved per user and per scene.
 * A failed save keeps the change on screen and shows a short error line.
 */

import { candidateSearch } from '../candidate-search.js';

export const ID_PLACEHOLDER = '__ID__';

/** The server sends URL templates, so JS builds no route strings. */
export function fillUrl(template, id) {
    return template.replace(ID_PLACEHOLDER, String(id));
}

function fillLabel(template, name) {
    return (template ?? '').replace(':name', name);
}

/** Set the label of an `x-icon-button`: its title and its screen-reader text. */
function setButtonLabel(button, label) {
    if (!button) return;

    button.title = label;

    const text = button.querySelector('.sr-only');

    if (text) text.textContent = label;
}

export function registerEditorCards(Alpine) {
    Alpine.data('editorCards', (config = {}) => ({
        ...candidateSearch({
            url: config.candidatesUrl,
            params: () => ({ type: config.type }),
            debounceMs: config.debounceMs,
            failureMessage: config.searchFailureMessage,
        }),

        loading: false,
        loadError: '',
        saveError: '',

        clearSearch() {
            // A slow answer to the old query must not fill the cleared list.
            this.searchSeq++;
            this.searching = false;
            this.resetSearch();
        },

        onInput() {
            if (this.query.trim() === '') {
                this.clearSearch();

                return;
            }

            this.queueSearch();
        },

        pickHighlighted() {
            const candidate = this.highlightedResult();

            if (candidate) this.pick(candidate);
        },

        findCard(id) {
            return this.$refs.cards.querySelector(`[data-editor-card][data-card-id="${id}"]`);
        },

        /** Show the picked card on top. An open card is not added twice: it expands and gets focus. */
        async pick(candidate) {
            this.clearSearch();
            this.loadError = '';

            if (this.reveal(candidate.id)) return;

            this.loading = true;

            let html;

            try {
                html = (await window.axios.get(fillUrl(config.cardUrl, candidate.id))).data;
            } catch {
                this.loadError = config.loadFailureMessage;

                return;
            } finally {
                this.loading = false;
            }

            // A second pick of the same entry can finish first.
            if (this.reveal(candidate.id)) return;

            const card = this.buildCard(candidate, html);

            this.$refs.cards.prepend(card);
            this.save(candidate.id, 'expanded');
            this.focusCard(card);
        },

        /** Expand and focus a card that is already open. False when there is none. */
        reveal(id) {
            const card = this.findCard(id);

            if (!card) return false;

            if (card.dataset.state !== 'expanded') {
                this.setState(card, 'expanded');
                this.save(id, 'expanded');
            }

            this.focusCard(card);

            return true;
        },

        buildCard(candidate, html) {
            const card = this.$refs.template.content.firstElementChild.cloneNode(true);
            const bodyId = `editor-card-${config.type}-${candidate.id}`;
            const body = card.querySelector('[data-card-body]');
            const view = card.querySelector('[data-card-view]');

            card.dataset.cardId = String(candidate.id);
            card.querySelector('[data-card-title]').textContent = candidate.label;
            card.querySelector('[data-card-action="toggle"]').setAttribute('aria-controls', bodyId);
            body.id = bodyId;
            // The server renders and escapes this partial.
            body.innerHTML = html;
            view.href = fillUrl(config.viewUrl, candidate.id);
            setButtonLabel(view, fillLabel(config.viewLabel, candidate.label));
            setButtonLabel(card.querySelector('[data-card-action="close"]'), fillLabel(config.closeLabel, candidate.label));
            this.setState(card, 'expanded');

            return card;
        },

        /** One listener for all cards, also for the cards that a pick adds later. */
        onCardClick(event) {
            const button = event.target.closest?.('[data-card-action]');

            if (!button) return;

            const card = button.closest('[data-editor-card]');

            if (button.dataset.cardAction === 'toggle') {
                this.toggle(card);
            } else if (button.dataset.cardAction === 'close') {
                this.close(card);
            }
        },

        toggle(card) {
            const state = card.dataset.state === 'expanded' ? 'collapsed' : 'expanded';

            this.setState(card, state);
            this.save(card.dataset.cardId, state);
        },

        close(card) {
            const id = card.dataset.cardId;

            card.remove();
            this.save(id, 'closed');
            // The focused button is gone, so focus goes back to the section search.
            this.$refs.query?.focus();
        },

        setState(card, state) {
            card.dataset.state = state;
            card.querySelector('[data-card-body]').hidden = state !== 'expanded';
            card.querySelector('[data-card-action="toggle"]').setAttribute('aria-expanded', String(state === 'expanded'));
        },

        focusCard(card) {
            card.querySelector('[data-card-action="toggle"]')?.focus();
            card.scrollIntoView?.({ block: 'nearest' });
        },

        async save(id, state) {
            try {
                await window.axios.put(config.saveUrl, { type: config.type, id: Number(id), state });
                this.saveError = '';
            } catch {
                this.saveError = config.saveFailureMessage;
            }
        },
    }));
}
