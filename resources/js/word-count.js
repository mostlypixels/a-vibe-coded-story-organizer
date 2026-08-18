/** Estimate with whitespace. The server supplies the authoritative count. */
export function countWords(text) {
    const trimmed = (text ?? '').trim();

    if (trimmed === '') {
        return 0;
    }

    return trimmed.split(/\s+/u).length;
}

/** Use server-provided templates and the server's number-grouping convention. */
export function formatCount(count, templates) {
    const template = count === 0 ? templates.zero : count === 1 ? templates.one : templates.other;

    return template.replace('%d', count.toLocaleString('en-US'));
}

export const DEBOUNCE_MS = 150;

export function registerWordCount(Alpine) {
    Alpine.data('wordCount', (config = {}) => ({
        count: config.initialCount ?? 0,
        templates: config.templates ?? { zero: '%d', one: '%d', other: '%d' },
        pendingText: '',
        pendingTimer: null,

        init() {
            this._onInput = (event) => {
                // Ignore input from other controls in the component.
                if (event.target.tagName !== 'TEXTAREA') {
                    return;
                }

                this.scheduleRecount(event.target.value);
            };
            this._onEditorTextChanged = (event) => this.scheduleRecount(event.detail.text);
            this._onReconcile = (event) => this.reconcile(event.detail.wordCount);

            this.$root.addEventListener('input', this._onInput);
            this.$root.addEventListener('wysiwyg:text-changed', this._onEditorTextChanged);
            this.$root.addEventListener('word-count:reconcile', this._onReconcile);
        },

        destroy() {
            clearTimeout(this.pendingTimer);
            this.$root.removeEventListener('input', this._onInput);
            this.$root.removeEventListener('wysiwyg:text-changed', this._onEditorTextChanged);
            this.$root.removeEventListener('word-count:reconcile', this._onReconcile);
        },

        scheduleRecount(text) {
            this.pendingText = text;
            clearTimeout(this.pendingTimer);
            this.pendingTimer = setTimeout(() => this.recount(), DEBOUNCE_MS);
        },

        recount() {
            this.count = countWords(this.pendingText);
        },

        /** Cancel the estimate before applying the server count. */
        reconcile(wordCount) {
            clearTimeout(this.pendingTimer);
            this.count = wordCount;
        },

        displayText() {
            return formatCount(this.count, this.templates);
        },
    }));
}
