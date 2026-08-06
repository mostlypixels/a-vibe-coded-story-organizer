/**
 * The indicative in-field word counter. Lives beside
 * `resources/js/autosave/field.js` conceptually but knows nothing about it
 * directly — the two talk only through a DOM CustomEvent (see
 * `registerWordCount()`'s docblock below), the same arm's-length pattern
 * `navigation-guard.js` already uses for `autosave:explicit-leave`.
 *
 * `countWords()` is deliberately the cheapest possible count: split on
 * whitespace, nothing else. No fence-stripping, no non-word filtering
 * (`architecture.md`'s "The live counter (JS)"). Duplicating
 * `App\Support\WordCounter`'s Markdown-aware rules here would mean shipping a
 * second Markdown parser to the browser to produce a number that is about to
 * be thrown away the moment the server's own count arrives — see `reconcile()`.
 */

/** Whitespace-only split, matching Word/Scrivener rather than this app's own
 *  stricter server-side rule (architecture.md's deliberate simplification). */
export function countWords(text) {
    const trimmed = (text ?? '').trim();

    if (trimmed === '') {
        return 0;
    }

    return trimmed.split(/\s+/u).length;
}

/**
 * Render a count through this app's one pluralisation convention without
 * hardcoding English words or a thousands separator here. `templates` holds
 * the three already-translated branches from
 * `App\Support\WordCountFormat::jsTemplates()` (zero/one/other), each still
 * carrying a literal `%d` in place of the number — this function only picks
 * the right branch and fills it in. `toLocaleString('en-US')`, not a
 * hand-rolled comma insert, does the thousands grouping — pinned to en-US so
 * it matches `App\Support\WordCountFormat::text()`'s `number_format()`
 * exactly, which (like every other formatted number in this app) is always
 * comma-grouped regardless of the project's own language.
 */
export function formatCount(count, templates) {
    const template = count === 0 ? templates.zero : count === 1 ? templates.one : templates.other;

    return template.replace('%d', count.toLocaleString('en-US'));
}

/** ui.md / architecture.md: "debounced ~150ms" — short, because unlike the
 *  autosave PATCH itself (2s, routes/web.php's throttle window) nothing is
 *  sent over the network here; it only exists to stop recounting on every
 *  single keystroke of a fast typist. */
export const DEBOUNCE_MS = 150;

/**
 * Alpine component for one `<x-autosave-field>`'s live counter. Mounted on a
 * wrapping `<div data-word-count>` that encloses that field's editor/textarea
 * *and* the counter's own `<span>` (see autosave-field.blade.php) — that
 * containment is what lets this component reach both halves of its job with
 * plain DOM events instead of reading another Alpine component's internals:
 *
 *   - Typing: a real `<textarea>`'s native `input` event bubbles up to
 *     `$root` on its own. For the TipTap-backed `x-wysiwyg` there is no
 *     native input event on the (hidden) fallback textarea — its `syncTextarea()`
 *     assigns `.value` directly, which fires nothing — so `wysiwyg.js`'s
 *     `onUpdate` instead dispatches a bubbling `wysiwyg:text-changed`
 *     CustomEvent carrying `editor.getText()`. `getText()` already returns
 *     *rendered* text even in markdown mode, because the editor holds a real
 *     ProseMirror document and only serialises to Markdown on save
 *     (`open-questions.md` Q7) — so this never sees raw `**bold**` markup.
 *
 *   - Reconciliation: `resources/js/autosave/field.js`'s `save()` looks up
 *     this same `[data-word-count]` element and dispatches
 *     `word-count:reconcile` on it with the server's authoritative
 *     `word_count` whenever a save succeeds. That is the *only* channel this
 *     component trusts for the true number — see `reconcile()`.
 */
export function registerWordCount(Alpine) {
    Alpine.data('wordCount', (config = {}) => ({
        count: config.initialCount ?? 0,
        templates: config.templates ?? { zero: '%d', one: '%d', other: '%d' },
        pendingText: '',
        pendingTimer: null,

        init() {
            this._onInput = (event) => {
                // Ignore bubbled input from anything that isn't the field's
                // own textarea (there is nothing else in this subtree today,
                // but this keeps the listener honest about what it reads).
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

        /**
         * Debounced recount: N rapid keystrokes only ever call {@see recount}
         * once, ~150ms after the last one. The *latest* text is captured
         * synchronously on every call (`pendingText`) so the eventual recount
         * is never stale, even though the count itself is delayed.
         */
        scheduleRecount(text) {
            this.pendingText = text;
            clearTimeout(this.pendingTimer);
            this.pendingTimer = setTimeout(() => this.recount(), DEBOUNCE_MS);
        },

        /** A separate, named method (rather than an inline setTimeout body) so
         *  the debounce itself is directly observable in tests — asserting on
         *  call count here, not just the number it lands on, is what proves
         *  N keystrokes only recount once (the resulting number alone can't
         *  tell debounced apart from not). */
        recount() {
            this.count = countWords(this.pendingText);
        },

        /**
         * The server's word count always wins, immediately — including when
         * it disagrees with whatever this component last estimated (e.g. a
         * fenced code block the estimate doesn't strip). Cancels any pending
         * debounced recount so a stale estimate can't overwrite the true
         * number moments later.
         */
        reconcile(wordCount) {
            clearTimeout(this.pendingTimer);
            this.count = wordCount;
        },

        /** What the `<span>` displays; re-evaluated by Alpine whenever `count` changes. */
        displayText() {
            return formatCount(this.count, this.templates);
        },
    }));
}
