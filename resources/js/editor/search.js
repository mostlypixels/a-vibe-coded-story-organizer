/**
 * Scene search in the left panel of the Write page.
 *
 * A result is a real link to the Write page of that scene. The navigation guard sees the click,
 * so a pending save is flushed before the page loads. An empty query shows the recent list.
 */

import { candidateSearch } from '../candidate-search.js';

export const SCENE_PLACEHOLDER = '__SCENE__';

/** The Write URL of a scene. The server sends the template, so JS builds no route strings. */
export function sceneUrl(template, candidate) {
    return template.replace(SCENE_PLACEHOLDER, String(candidate.id));
}

export function registerEditorSceneSearch(Alpine) {
    Alpine.data('editorSceneSearch', (config = {}) => ({
        ...candidateSearch({
            url: config.candidatesUrl,
            params: () => ({ type: 'scene' }),
            debounceMs: config.debounceMs,
            failureMessage: config.failureMessage,
        }),

        /** True while the recent list shows instead of results. */
        showingRecent() {
            return this.query.trim() === '';
        },

        urlFor(candidate) {
            return sceneUrl(config.sceneUrl ?? '', candidate);
        },

        onInput() {
            if (!this.showingRecent()) {
                this.queueSearch();

                return;
            }

            // A slow answer to the old query must not fill the cleared list.
            this.searchSeq++;
            this.searching = false;
            this.resetSearch();
        },

        onEscape() {
            this.searchSeq++;
            this.searching = false;
            this.resetSearch();
        },

        /** Click the highlighted link, so the leave guard runs. */
        openHighlighted() {
            const links = this.$refs?.results?.querySelectorAll('a') ?? [];

            links[this.highlighted]?.click();
        },
    }));
}
