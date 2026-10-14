/**
 * Search state for a picker dialog: the note-link picker and the editor "Link to…" dialog.
 * Spread the result into an Alpine component. Methods use `this`, so the component owns the state.
 */

export const DEFAULT_DEBOUNCE_MS = 250;

/**
 * @param {object} options
 * @param {string} options.url The candidates endpoint.
 * @param {(component: object) => object} [options.params] Query parameters other than `q`.
 * @param {number} [options.debounceMs]
 * @param {string} [options.failureMessage] Shown in `error` when the search fails.
 */
export function candidateSearch({ url, params = () => ({}), debounceMs = DEFAULT_DEBOUNCE_MS, failureMessage = '' }) {
    return {
        query: '',
        results: [],
        searching: false,
        error: '',
        // The result that the arrow keys select.
        highlighted: 0,

        // Each search gets a number, so a slow old response cannot replace a newer one.
        searchSeq: 0,
        searchTimer: null,

        resetSearch() {
            clearTimeout(this.searchTimer);
            this.query = '';
            this.results = [];
            this.error = '';
            this.highlighted = 0;
        },

        queueSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.search(), debounceMs);
        },

        async search() {
            clearTimeout(this.searchTimer);

            const seq = ++this.searchSeq;

            this.searching = true;

            try {
                const response = await window.axios.get(url, { params: { q: this.query, ...params(this) } });

                if (seq !== this.searchSeq) return;

                this.results = response.data;
                this.error = '';
            } catch {
                if (seq !== this.searchSeq) return;

                this.results = [];
                this.error = failureMessage;
            }

            this.highlighted = 0;
            this.searching = false;
        },

        /** Move the highlight by `step` and wrap at the ends. */
        move(step) {
            const count = this.results.length;

            if (count === 0) return;

            this.highlighted = (this.highlighted + step + count) % count;
        },

        highlightedResult() {
            return this.results[this.highlighted] ?? null;
        },
    };
}
