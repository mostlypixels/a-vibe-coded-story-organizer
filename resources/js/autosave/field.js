/**
 * The Alpine adapter for a single `<x-autosave-field>` instance. Thin by
 * design: every *decision* (state transitions, retry timing) lives in
 * `./store.js`; this file only wires DOM events and calls
 * `window.axios` — the same `window.axios` global `bootstrap.js` configures (no
 * separate axios import, so this shares the one instance's interceptors/defaults).
 *
 * The server is the only store. Nothing here writes to `localStorage`: unsaved text
 * lives in the DOM until a PATCH lands, and the navigation guard
 * (`resources/js/navigation-guard.js`) is what stops the writer leaving with it.
 *
 * Dirty-only, a binding rule: nothing here
 * fires a PATCH, not even a debounce tick, until a real `input` event has been seen.
 * Opening a record to read it writes nothing.
 *
 * The underlying value is always read via `this.$root.querySelector('textarea')`,
 * never Alpine's `$refs` — `<x-wysiwyg>` mounts its own nested `x-data` scope, and
 * Alpine's `$refs` only resolves refs declared in the *current* component's scope, so
 * a parent component cannot reach a nested component's `x-ref="textarea"` that way.
 * The progressive-enhancement `<textarea>` (see wysiwyg.blade.php) is always a plain
 * DOM node underneath, kept in sync by wysiwyg.js's own `syncTextarea()` on every
 * edit, so a native `querySelector` reaches it regardless of which kind (`plain` or
 * wysiwyg-wrapped) this field is.
 */

import { mapResponse, retryDelayMs, scheduleRetry, worstState, STATES } from './store';

/**
 * How long to wait after the last keystroke before an automatic autosave PATCH
 * fires. Matches the "2-second debounce" rate-limit rationale already written into
 * `routes/web.php`'s `throttle:120,1` comment for this endpoint.
 */
export const DEBOUNCE_MS = 2000;

/**
 * How long a `saved` state lingers before fading back to `idle` (ui.md: "fades
 * after saved").
 */
export const SAVED_FADE_MS = 2000;

/**
 * The identity key for a field, `entity:id:field`. It names nothing stored
 * anywhere — it is the map key under which the store tracks this field's state,
 * element and dirty flag.
 */
export function fieldKeyFor({ entity, id, field }) {
    return `${entity}:${id}:${field}`;
}

/**
 * The dirty-only gate, applied identically to the debounce tick, blur, and Ctrl-S:
 * a save is only ever attempted once the writer has produced a real edit event in
 * this field (`dirty`) — and only against an existing entity (`id` set). A create
 * form has no id to PATCH against yet, so nothing autosaves until the entity exists.
 */
export function shouldAutosave(dirty, id) {
    return dirty === true && id !== null && id !== undefined;
}

export function registerAutosaveField(Alpine) {
    // The shared cross-field store the global lower-right badge reads.
    // Guarded so re-registering (e.g. in tests) doesn't clobber live field state.
    if (!Alpine.store('autosave')) {
        Alpine.store('autosave', {
            fields: {},
            elements: {},
            // key => boolean, mirrors each field's own `dirty` flag. Distinct from
            // `fields` (the STATES machine value): a field is dirty from the first
            // keystroke until a successful save/flush, including the ~2s debounce
            // window where `state` is still `idle` — exactly the window the
            // data-loss-warnings navigation guard and beforeunload fallback exist
            // to protect.
            dirty: {},

            /** Worst-state-wins across every field currently on the page (ui.md). */
            worstState() {
                return worstState(Object.values(this.fields));
            },

            /** Is anything on this page unsaved right now? The one signal the
             *  navigation guard and its beforeunload fallback both read. */
            isDirty() {
                return Object.values(this.dirty).some(Boolean);
            },
        });
    }

    Alpine.data('autosaveField', (config = {}) => ({
        key: fieldKeyFor(config),
        dirty: false,
        state: STATES.IDLE,
        attempt: 0,
        pendingTimer: null,
        wasReplay: false,
        baseHash: config.baseHash,

        init() {
            const store = Alpine.store('autosave');
            store.fields[this.key] = this.state;
            store.elements[this.key] = this.$el;

            this._onInput = () => this.onInput();
            this._onFocusOut = () => this.flush();
            this._onKeydown = (event) => this.onKeydown(event);
            this._onWindowFocus = () => this.replayIfQueued();

            this.$root.addEventListener('input', this._onInput);
            this.$root.addEventListener('focusout', this._onFocusOut);
            this.$root.addEventListener('keydown', this._onKeydown);
            window.addEventListener('focus', this._onWindowFocus);
            document.addEventListener('visibilitychange', this._onWindowFocus);
        },

        destroy() {
            this.$root.removeEventListener('input', this._onInput);
            this.$root.removeEventListener('focusout', this._onFocusOut);
            this.$root.removeEventListener('keydown', this._onKeydown);
            window.removeEventListener('focus', this._onWindowFocus);
            document.removeEventListener('visibilitychange', this._onWindowFocus);

            const store = Alpine.store('autosave');
            delete store.fields[this.key];
            delete store.elements[this.key];
            delete store.dirty[this.key];
        },

        setState(next) {
            this.state = next;
            Alpine.store('autosave').fields[this.key] = next;
        },

        /** The current value of the always-present real `<textarea>` (see file docblock). */
        fieldValue() {
            const textarea = this.$root.querySelector('textarea');

            return textarea ? textarea.value : '';
        },

        /**
         * Snap the live in-field counter (resources/js/word-count.js) to the
         * authoritative number the server just persisted.
         * That component lives in its own nested Alpine scope on a
         * `[data-word-count]` element inside this field, reached the same
         * DOM-querying way `fieldValue()` reaches the textarea rather than via
         * Alpine internals — the file docblock explains why `$refs` can't cross
         * a nested `x-data` boundary here. Dispatched unconditionally: even if
         * the writer has kept typing since this save left, `word_count`
         * describes what this request actually wrote, and the counter must
         * reflect the true stored count rather than sit on a stale estimate.
         * A field rendered without a counter (only ever in a test) is a no-op.
         */
        notifyWordCount(wordCount) {
            const counter = this.$root.querySelector('[data-word-count]');

            if (counter && typeof wordCount === 'number') {
                counter.dispatchEvent(new CustomEvent('word-count:reconcile', { detail: { wordCount } }));
            }
        },

        /**
         * The dirty-only gate: the very first real edit event flips `dirty`, and every
         * edit (re)starts the debounce timer. A create form (no `config.id` yet) still
         * goes dirty — the navigation guard must warn about text it cannot yet PATCH —
         * but nothing is scheduled for it.
         */
        onInput() {
            this.dirty = true;
            Alpine.store('autosave').dirty[this.key] = true;

            if (!shouldAutosave(this.dirty, config.id)) {
                return;
            }

            clearTimeout(this.pendingTimer);
            this.pendingTimer = setTimeout(() => this.save({}), DEBOUNCE_MS);
        },

        /**
         * Ctrl-S is a flush, not a permanent checkpoint: it sends
         * `run_matcher: true` (a coarse trigger) so the autosave lands immediately and
         * closes the coalescing window. It never creates a manual revision — the
         * permanent, labeled manual checkpoint is the full-form Save button's job, and
         * that is recorded server-side by the entity controllers, not by this endpoint.
         */
        onKeydown(event) {
            const isSaveShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's';

            if (!isSaveShortcut) {
                return;
            }

            event.preventDefault();

            this.flush({ runMatcher: true });
        },

        /** Blur/Ctrl-S: send immediately, cancelling any pending debounce tick. Never fires on a clean (non-dirty) field. */
        flush(options = {}) {
            clearTimeout(this.pendingTimer);

            if (!shouldAutosave(this.dirty, config.id)) {
                return;
            }

            this.save(options);
        },

        async save({ runMatcher = false } = {}) {
            const value = this.fieldValue();

            this.setState(STATES.SAVING);

            let status = null;
            let headers = {};
            let data = null;

            try {
                const response = await window.axios.patch(config.url, {
                    value,
                    base_hash: this.baseHash,
                    run_matcher: runMatcher,
                });

                status = response.status;
                headers = response.headers;
                data = response.data;
            } catch (error) {
                if (error.response) {
                    status = error.response.status;
                    headers = error.response.headers;
                }
            }

            const { state, retryAfterMs } = mapResponse(status, { headers, wasReplay: this.wasReplay });

            // Only a 403 immediately following a session-expired attempt is a
            // "replay" — every other outcome resets the flag (store.js's own docblock).
            this.wasReplay = state === STATES.SESSION_EXPIRED;

            if (state === STATES.SAVED) {
                // This response is about `value`, the text as it was when the PATCH
                // left. If the writer kept typing while it was in flight, the field
                // has moved on and is still unsaved: `onInput()` already scheduled a
                // debounce tick that will send the newer text a couple of seconds
                // later. Clearing `dirty` here would tell the navigation guard and the
                // beforeunload fallback that everything is saved during exactly the
                // window they exist to cover — the tab would close without a word.
                const settled = this.fieldValue() === value;

                if (settled) {
                    this.dirty = false;
                    Alpine.store('autosave').dirty[this.key] = false;
                }

                this.attempt = 0;
                // Adopt the server's hash, never write `data.value` back into
                // the editor DOM (would yank the caret mid-sentence). Adopted even
                // when the field has moved on: the server stored what this request
                // sent, so the pending save must build on that hash or it 409s.
                this.baseHash = data.hash;
                this.notifyWordCount(data.word_count);
                this.setState(state);
                setTimeout(() => {
                    if (this.state === STATES.SAVED) {
                        this.setState(STATES.IDLE);
                    }
                }, SAVED_FADE_MS);

                return;
            }

            this.setState(state);

            if (state === STATES.RETRYING) {
                this.attempt += 1;
                scheduleRetry(() => this.save({ runMatcher }), retryDelayMs(this.attempt, retryAfterMs));
            }
        },

        /** Auto-replay a stuck session-expired save on tab focus or visibility. */
        replayIfQueued() {
            if (this.state === STATES.SESSION_EXPIRED && this.dirty && document.visibilityState !== 'hidden') {
                this.save({});
            }
        },

    }));
}
