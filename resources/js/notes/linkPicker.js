/**
 * The "Add link" dialog and the unlink buttons for note links. It works from both sides:
 *
 * - On a note, the writer picks a type, searches entities of that type, and links one.
 * - On an entity page, `linkType` and `linkId` name the entity. The writer searches
 *   notes, and the picked note's id fills `__NOTE__` in `storeUrl`.
 *
 * After a change, the component fetches the page again and swaps only the list under
 * `refreshSelector`. A full reload would lose unsaved edits in the page form.
 */

import { candidateSearch } from '../candidate-search.js';

/** Replace the element under `selector` with its copy from a fresh render of the page. */
export async function refreshList(selector) {
    const current = selector ? document.querySelector(selector) : null;

    if (!current) return;

    const response = await window.axios.get(window.location.href, { headers: { Accept: 'text/html' } });
    const fresh = new DOMParser().parseFromString(response.data, 'text/html').querySelector(selector);

    if (fresh) current.innerHTML = fresh.innerHTML;
}

export function registerLinkPicker(Alpine) {
    Alpine.data('linkPicker', (config = {}) => ({
        ...candidateSearch({
            url: config.candidatesUrl,
            // The entity side searches notes only, so it sends no type.
            params: (picker) => (picker.entitySide() ? {} : { type: picker.type }),
            debounceMs: config.debounceMs,
            failureMessage: config.searchFailureMessage ?? config.failureMessage,
        }),

        type: config.type ?? '',
        busy: false,

        entitySide() {
            return Boolean(config.linkType);
        },

        open() {
            this.resetSearch();
            this.busy = false;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: config.dialog }));
            this.search();
        },

        close() {
            window.dispatchEvent(new CustomEvent('close-modal', { detail: config.dialog }));
        },

        label(candidate) {
            return candidate.label ?? candidate.title;
        },

        async pick(candidate) {
            if (this.busy) return;

            const [url, body] = this.entitySide()
                ? [String(config.storeUrl).replace('__NOTE__', candidate.id), { type: config.linkType, id: config.linkId }]
                : [config.storeUrl, { type: candidate.type, id: candidate.id }];

            await this.send(() => window.axios.post(url, body), () => this.close());
        },

        async unlink(url) {
            await this.send(() => window.axios.delete(url));
        },

        async send(request, onSuccess = () => {}) {
            this.busy = true;
            this.error = '';

            try {
                await request();
            } catch (error) {
                const response = error && error.response;

                this.error = response && response.status === 422
                    ? (response.data?.message ?? config.failureMessage)
                    : config.failureMessage;
                this.busy = false;

                return;
            }

            onSuccess();

            try {
                await refreshList(config.refreshSelector);
            } catch {
                window.location.reload();
            }

            this.busy = false;
        },
    }));
}
