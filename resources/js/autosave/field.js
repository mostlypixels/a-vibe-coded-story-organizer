/** Connect an autosave field to the state machine and server. */

import { mapResponse, retryDelayMs, scheduleRetry, worstState, STATES } from './store';

export const DEBOUNCE_MS = 2000;

export const SAVED_FADE_MS = 2000;

export function fieldKeyFor({ entity, id, field }) {
    return `${entity}:${id}:${field}`;
}

/** Do not autosave an unchanged field or an entity that does not exist. */
export function shouldAutosave(dirty, id) {
    return dirty === true && id !== null && id !== undefined;
}

export function registerAutosaveField(Alpine) {
    // Do not replace live state when Alpine registers this component again.
    if (!Alpine.store('autosave')) {
        Alpine.store('autosave', {
            fields: {},
            elements: {},
            dirty: {},
            flushers: {},

            worstState() {
                return worstState(Object.values(this.fields));
            },

            isDirty() {
                return Object.values(this.dirty).some(Boolean);
            },

            /** Force a specific field's pending save and await it, without reaching into the field. */
            flush(key, options = {}) {
                const flusher = this.flushers[key];

                return flusher ? flusher(options) : Promise.resolve();
            },
        });
    }

    Alpine.data('autosaveField', (config = {}) => ({
        key: fieldKeyFor(config),
        dirty: false,
        state: STATES.IDLE,
        attempt: 0,
        pendingTimer: null,
        retryTimer: null,
        inFlight: null,
        queuedSave: null,
        wasReplay: false,
        baseHash: config.baseHash,

        init() {
            const store = Alpine.store('autosave');
            store.fields[this.key] = this.state;
            store.elements[this.key] = this.$el;
            store.flushers[this.key] = (options) => this.flush(options);

            this._onInput = () => this.onInput();
            this._onFocusOut = () => this.flush();
            this._onKeydown = (event) => this.onKeydown(event);
            this._onWindowFocus = () => this.replayIfQueued();

            // ProseMirror changes do not always emit a native input event.
            this.$root.addEventListener('input', this._onInput);
            this.$root.addEventListener('wysiwyg:text-changed', this._onInput);
            this.$root.addEventListener('focusout', this._onFocusOut);
            this.$root.addEventListener('keydown', this._onKeydown);
            window.addEventListener('focus', this._onWindowFocus);
            document.addEventListener('visibilitychange', this._onWindowFocus);
        },

        destroy() {
            clearTimeout(this.retryTimer);
            this.$root.removeEventListener('input', this._onInput);
            this.$root.removeEventListener('wysiwyg:text-changed', this._onInput);
            this.$root.removeEventListener('focusout', this._onFocusOut);
            this.$root.removeEventListener('keydown', this._onKeydown);
            window.removeEventListener('focus', this._onWindowFocus);
            document.removeEventListener('visibilitychange', this._onWindowFocus);

            const store = Alpine.store('autosave');
            delete store.fields[this.key];
            delete store.elements[this.key];
            delete store.dirty[this.key];
            delete store.flushers[this.key];
        },

        setState(next) {
            this.state = next;
            Alpine.store('autosave').fields[this.key] = next;
        },

        fieldValue() {
            // Alpine refs cannot cross the nested WYSIWYG component boundary.
            const textarea = this.$root.querySelector('textarea');

            return textarea ? textarea.value : '';
        },

        notifyWordCount(wordCount) {
            const counter = this.$root.querySelector('[data-word-count]');

            if (counter && typeof wordCount === 'number') {
                counter.dispatchEvent(new CustomEvent('word-count:reconcile', { detail: { wordCount } }));
            }
        },

        onInput() {
            this.dirty = true;
            Alpine.store('autosave').dirty[this.key] = true;

            if (!shouldAutosave(this.dirty, config.id)) {
                return;
            }

            clearTimeout(this.pendingTimer);
            this.pendingTimer = setTimeout(() => this.save({}), DEBOUNCE_MS);
        },

        /** Ctrl-S flushes autosave. It does not create a manual revision. */
        onKeydown(event) {
            const isSaveShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's';

            if (!isSaveShortcut) {
                return;
            }

            event.preventDefault();

            this.flush({ runMatcher: true });
        },

        flush(options = {}) {
            clearTimeout(this.pendingTimer);

            if (!shouldAutosave(this.dirty, config.id)) {
                return Promise.resolve();
            }

            return this.save(options);
        },

        /**
         * Send one request at a time. A second request would send the old
         * base_hash and get a false 409 after the first request succeeds.
         */
        save({ runMatcher = false } = {}) {
            clearTimeout(this.retryTimer);
            this.retryTimer = null;

            if (this.inFlight) {
                this.queuedSave = { runMatcher: runMatcher || (this.queuedSave?.runMatcher ?? false) };

                return this.inFlight;
            }

            this.inFlight = this.sendUntilSettled({ runMatcher }).finally(() => {
                this.inFlight = null;
            });

            return this.inFlight;
        },

        async sendUntilSettled(options) {
            let next = options;

            while (next) {
                this.queuedSave = null;
                const state = await this.send(next);
                const queued = this.queuedSave;
                next = null;

                // After a failure, the retry or the next edit sends the latest text.
                if (queued && state === STATES.SAVED && this.dirty) {
                    next = queued;
                }
            }
        },

        async send({ runMatcher = false }) {
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

            // Only a save after session expiry is a replay.
            this.wasReplay = state === STATES.SESSION_EXPIRED;

            if (state === STATES.SAVED) {
                // Do not clear dirty when the user typed during the request.
                const settled = this.fieldValue() === value;

                if (settled) {
                    this.dirty = false;
                    Alpine.store('autosave').dirty[this.key] = false;
                }

                this.attempt = 0;
                // Use the stored hash for the next save. Do not replace editor text.
                this.baseHash = data.hash;
                this.notifyWordCount(data.word_count);
                this.setState(state);
                setTimeout(() => {
                    if (this.state === STATES.SAVED) {
                        this.setState(STATES.IDLE);
                    }
                }, SAVED_FADE_MS);

                return state;
            }

            this.setState(state);

            if (state === STATES.RETRYING) {
                this.attempt += 1;
                // Keep a matcher request that arrived during this request.
                const retryMatcher = runMatcher || (this.queuedSave?.runMatcher ?? false);
                this.retryTimer = scheduleRetry(
                    () => this.save({ runMatcher: retryMatcher }),
                    retryDelayMs(this.attempt, retryAfterMs),
                );
            }

            return state;
        },

        replayIfQueued() {
            if (this.state === STATES.SESSION_EXPIRED && this.dirty && document.visibilityState !== 'hidden') {
                this.save({});
            }
        },

    }));
}
