/** Connect an autosave field to the state machine and server. */

import { labelFor } from './badge';
import { mapResponse, retryDelayMs, scheduleRetry, worstState, STATES } from './store';

export const DEBOUNCE_MS = 2000;

export const SAVED_FADE_MS = 2000;

/** Tabs of this app use this channel to tell each other which field they edit. */
export const TAB_CHANNEL = 'imagoldfish-autosave';

/** Browsers without BroadcastChannel get no tab lock. The 409 choice still protects the text. */
export function openTabChannel() {
    return typeof BroadcastChannel === 'function' ? new BroadcastChannel(TAB_CHANNEL) : null;
}

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
            locked: {},
            unlockers: {},

            worstState() {
                const locked = Object.keys(this.locked).filter((key) => this.locked[key]).map(() => STATES.LOCKED);

                return worstState([...Object.values(this.fields), ...locked]);
            },

            /** Unlock every field of this page, so the other tab locks instead. */
            takeOver() {
                Object.keys(this.locked).filter((key) => this.locked[key]).forEach((key) => this.unlockers[key]?.());
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
        // The text changed since the last matcher run. A clean field still needs one.
        matcherPending: false,
        baseHash: config.baseHash,
        // Another tab opened this field. Only one tab may write, or the tabs overwrite each other (#258).
        locked: false,
        // What the server holds after a 409: { value, hash }.
        conflict: null,
        channel: null,

        get label() {
            return labelFor(this.state, config.strings);
        },

        init() {
            const store = Alpine.store('autosave');
            store.fields[this.key] = this.state;
            store.elements[this.key] = this.$el;
            store.flushers[this.key] = (options) => this.flush(options);
            store.unlockers[this.key] = () => this.takeOver();

            this._onInput = () => this.onInput();
            this._onFocusOut = () => this.flush({ runMatcher: true });
            this._onKeydown = (event) => this.onKeydown(event);
            this._onWindowFocus = () => this.replayIfQueued();

            // ProseMirror changes do not always emit a native input event.
            this.$root.addEventListener('input', this._onInput);
            this.$root.addEventListener('wysiwyg:text-changed', this._onInput);
            this.$root.addEventListener('focusout', this._onFocusOut);
            this.$root.addEventListener('keydown', this._onKeydown);
            window.addEventListener('focus', this._onWindowFocus);
            document.addEventListener('visibilitychange', this._onWindowFocus);

            // A create form has no id, so it has nothing to overwrite.
            if (config.id !== null && config.id !== undefined) {
                this.channel = openTabChannel();

                if (this.channel) {
                    this.channel.onmessage = (event) => this.onTabMessage(event.data);
                    this.channel.postMessage({ type: 'claim', key: this.key });
                }
            }
        },

        destroy() {
            clearTimeout(this.retryTimer);
            this.$root.removeEventListener('input', this._onInput);
            this.$root.removeEventListener('wysiwyg:text-changed', this._onInput);
            this.$root.removeEventListener('focusout', this._onFocusOut);
            this.$root.removeEventListener('keydown', this._onKeydown);
            window.removeEventListener('focus', this._onWindowFocus);
            document.removeEventListener('visibilitychange', this._onWindowFocus);
            this.channel?.close();

            const store = Alpine.store('autosave');
            delete store.fields[this.key];
            delete store.elements[this.key];
            delete store.dirty[this.key];
            delete store.flushers[this.key];
            delete store.locked[this.key];
            delete store.unlockers[this.key];
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

        /** The field does not know where the page shows the list, so it announces it. */
        notifyReferences(html) {
            if (typeof html === 'string') {
                window.dispatchEvent(new CustomEvent('codex-references-synced', { detail: { html } }));
            }
        },

        /** The newest tab wins. A tab that saved tells the others, so a clean tab stays current. */
        onTabMessage(message) {
            if (!message || message.key !== this.key) {
                return;
            }

            if (message.type === 'claim') {
                this.lock();

                return;
            }

            if (message.type === 'saved' && typeof message.value === 'string' && !this.dirty && !this.conflict) {
                this.replaceValue(message.value);
                this.baseHash = message.hash;
                this.notifyWordCount(message.wordCount);
            }
        },

        lock() {
            // Save what this tab has, so the other tab can take it over.
            this.flush({ runMatcher: true });
            this.locked = true;
            Alpine.store('autosave').locked[this.key] = true;
            this.$root.inert = true;
        },

        takeOver() {
            this.locked = false;
            Alpine.store('autosave').locked[this.key] = false;
            this.$root.inert = false;
            this.channel?.postMessage({ type: 'claim', key: this.key });
        },

        /** Put text in the field without a change event. The editor listens for the event below. */
        replaceValue(value) {
            const textarea = this.$root.querySelector('textarea');

            if (!textarea) {
                return;
            }

            textarea.value = value;
            textarea.dispatchEvent(new CustomEvent('autosave:value-replaced', { detail: { value }, bubbles: true }));
        },

        /** Save this tab's text over the newer text. */
        keepMine() {
            return this.resolveConflict({ loadSaved: false });
        },

        /** Take the newer text. This tab's text goes to History first. */
        loadSaved() {
            return this.resolveConflict({ loadSaved: true });
        },

        /** Both texts get their own revision, so History keeps each of them. */
        async resolveConflict({ loadSaved }) {
            if (!this.conflict) {
                return;
            }

            const saved = this.conflict;
            this.conflict = null;
            this.baseHash = saved.hash;

            const state = await this.send({ runMatcher: !loadSaved, newRevision: true });

            if (state !== STATES.SAVED || !loadSaved) {
                return;
            }

            this.replaceValue(saved.value);
            await this.send({ runMatcher: true, newRevision: true });
        },

        onInput() {
            this.dirty = true;
            this.matcherPending = true;
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

            if (!shouldAutosave(this.dirty || this.needsMatcher(options), config.id)) {
                return Promise.resolve();
            }

            return this.save(options);
        },

        needsMatcher({ runMatcher = false } = {}) {
            return runMatcher && config.matcher === true && this.matcherPending;
        },

        /**
         * Send one request at a time. A second request would send the old
         * base_hash and get a false 409 after the first request succeeds.
         */
        save({ runMatcher = false } = {}) {
            clearTimeout(this.retryTimer);
            this.retryTimer = null;

            // Every save would get a 409 again. The writer must choose first.
            if (this.conflict) {
                return Promise.resolve();
            }

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
                // A matcher request goes out with unchanged text too: the server runs
                // the matcher even when it records no revision (#199).
                if (queued && state === STATES.SAVED && (this.dirty || queued.runMatcher)) {
                    next = queued;
                }
            }
        },

        async send({ runMatcher = false, newRevision = false }) {
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
                    new_revision: newRevision,
                });

                status = response.status;
                headers = response.headers;
                data = response.data;
            } catch (error) {
                if (error.response) {
                    status = error.response.status;
                    headers = error.response.headers;
                    data = error.response.data;
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

                    if (runMatcher) {
                        this.matcherPending = false;
                    }
                }

                this.attempt = 0;
                // Use the stored hash for the next save. Do not replace editor text.
                this.baseHash = data.hash;
                this.notifyWordCount(data.word_count);
                this.notifyReferences(data.referenced_entries_html);
                this.channel?.postMessage({ type: 'saved', key: this.key, value: data.value, hash: data.hash, wordCount: data.word_count });
                this.setState(state);
                setTimeout(() => {
                    if (this.state === STATES.SAVED) {
                        this.setState(STATES.IDLE);
                    }
                }, SAVED_FADE_MS);

                return state;
            }

            if (state === STATES.CONFLICT && typeof data?.hash === 'string') {
                this.conflict = { value: data.value ?? '', hash: data.hash };
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
