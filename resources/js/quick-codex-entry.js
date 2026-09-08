/**
 * The "New codex entry" dialog on the scene editor. It creates a name-and-type entry
 * through `scenes.codex-entries.store` and puts the scene's refreshed reference list back
 * into the sidebar, so the writer never leaves the paragraph they were writing.
 *
 * Two triggers open it, one code path: the sidebar button and (task 04) the editor slash
 * menu. Both dispatch `quick-codex-entry:open`, optionally carrying a name to prefill.
 *
 * The dialog never closes on an error. The typed name is the thing being protected.
 */

/** Name of the x-modal this component drives. */
export const DIALOG_NAME = 'quick-codex-entry';

/** The event both triggers dispatch. Detail: `{ name }`, both optional. */
export const OPEN_EVENT = 'quick-codex-entry:open';

/** Longer than `x-modal`'s own focus delay, so the name field wins it. */
export const MODAL_FOCUS_MS = 150;

/**
 * The editor selection, cloned so the dialog can put the caret back where it was.
 * Returns null when the selection is not inside the editor — then there is nothing to
 * prefill and nothing to restore.
 */
export function captureSelection(editable) {
    const selection = window.getSelection ? window.getSelection() : null;

    if (!editable || !selection || selection.rangeCount === 0) {
        return null;
    }

    const range = selection.getRangeAt(0);

    return editable.contains(range.commonAncestorContainer) ? range.cloneRange() : null;
}

/** Put the caret back at `range` and give the editor the keyboard again. */
export function restoreSelection(editable, range) {
    if (!editable) return;

    editable.focus();

    const selection = window.getSelection ? window.getSelection() : null;

    if (!range || !selection) return;

    selection.removeAllRanges();
    selection.addRange(range);
}

/**
 * Write "Added <a>:name</a> to the codex." into `node`, splitting the translated template
 * so the entry name stays a text node and carries the link.
 */
export function renderConfirmation(node, template, entry) {
    if (!node) return;

    node.textContent = '';

    const [before, after = ''] = String(template).split(':name');

    const link = document.createElement('a');
    link.href = entry.url;
    link.textContent = entry.name;
    link.className = 'text-link hover:text-link-hover underline';

    node.appendChild(document.createTextNode(before));
    node.appendChild(link);
    node.appendChild(document.createTextNode(after));
}

export function registerQuickCodexEntry(Alpine) {
    Alpine.data('quickCodexEntry', (config = {}) => ({
        name: '',
        type: config.defaultType ?? 'character',
        error: '',
        existingUrl: '',
        busy: false,

        // A DOM Range is not reactive state; keep it off the Alpine proxy.
        savedRange: null,
        wasOpened: false,

        init() {
            this._onOpen = (event) => this.openWith(event.detail ?? {});
            window.addEventListener(OPEN_EVENT, this._onOpen);
        },

        destroy() {
            window.removeEventListener(OPEN_EVENT, this._onOpen);
        },

        editable() {
            return config.editorSelector ? document.querySelector(config.editorSelector) : null;
        },

        openWith(detail = {}) {
            const editable = this.editable();

            this.savedRange = captureSelection(editable);

            const selected = this.savedRange ? this.savedRange.toString() : '';

            this.name = String(detail.name ?? selected).trim();
            this.type = config.defaultType ?? 'character';
            this.error = '';
            this.existingUrl = '';
            this.busy = false;
            this.wasOpened = true;

            window.dispatchEvent(new CustomEvent('open-modal', { detail: DIALOG_NAME }));

            // x-modal gives its own first control the keyboard shortly after it opens, so
            // the name field can only claim it afterwards. A dialog closed inside that
            // window gets the same treatment in reverse: the caret goes back to the prose.
            setTimeout(() => this.claimFocus(), MODAL_FOCUS_MS);
        },

        claimFocus() {
            if (!this.wasOpened) {
                restoreSelection(this.editable(), this.savedRange);

                return;
            }

            const input = config.nameSelector ? document.querySelector(config.nameSelector) : null;

            if (!input) return;

            input.focus();
            // A prefilled name is a suggestion; selecting it lets one keystroke replace it.
            input.select();
        },

        /** Idempotent: every close path calls it, and only the first call does the work. */
        restoreEditorFocus() {
            if (!this.wasOpened) return;

            this.wasOpened = false;

            restoreSelection(this.editable(), this.savedRange);
        },

        close() {
            window.dispatchEvent(new CustomEvent('close-modal', { detail: DIALOG_NAME }));
            this.restoreEditorFocus();
        },

        async submit() {
            if (this.busy) return;

            this.busy = true;
            this.error = '';
            this.existingUrl = '';

            // The endpoint matches the new name against the *stored* prose, so the
            // writer's own pending save must land first. runMatcher is false: the entry
            // does not exist yet, and the endpoint syncs the scene after creating it.
            const store = Alpine.store('autosave');
            await (store ? store.flush(config.autosaveKey, { runMatcher: false }) : Promise.resolve());

            let data;

            try {
                const response = await window.axios.post(config.url, { name: this.name, type: this.type });

                data = response.data;
            } catch (error) {
                this.showFailure(error);

                return;
            }

            this.replaceList(data.referenced_entries_html);
            renderConfirmation(document.querySelector(config.confirmationSelector), config.successMessage, data.entry);

            this.busy = false;
            this.close();
        },

        showFailure(error) {
            const response = error && error.response;

            if (response && response.status === 422) {
                this.error = response.data?.message ?? config.invalidMessage;

                const existingId = response.data?.existing_entry_id;

                if (existingId) {
                    this.existingUrl = String(config.entryUrlTemplate).replace('__ID__', existingId);
                }
            } else {
                this.error = config.failureMessage;
            }

            this.busy = false;
        },

        /**
         * The list is server-rendered HTML from the same Blade partial the page used, so
         * one template stays the only description of that list. The whole list is
         * replaced because the resync may also have dropped entries.
         */
        replaceList(html) {
            const list = document.querySelector(config.listSelector);

            if (list) list.innerHTML = html;
        },
    }));
}
