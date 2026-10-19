/**
 * The "New scene" dialog in the left panel of the Write page.
 *
 * It creates the scene in the background, then opens the new scene's Write page.
 * Before it leaves, it waits for every autosave. When a save fails, the leave guard asks the writer.
 * The dialog never closes on a validation error. The typed title is the thing being protected.
 */

import { ASK_EVENT, settleAutosaves } from '../navigation-guard.js';
import { MODAL_FOCUS_MS } from '../quick-codex-entry.js';

export const DIALOG_NAME = 'editor-new-scene';

/** The left panel button dispatches it. */
export const OPEN_EVENT = 'editor-new-scene:open';

export function registerEditorNewScene(Alpine) {
    Alpine.data('editorNewScene', (config = {}) => ({
        chapterId: String(config.chapterId ?? ''),
        name: '',
        error: '',
        busy: false,

        init() {
            this._onOpen = () => this.open();
            window.addEventListener(OPEN_EVENT, this._onOpen);
        },

        destroy() {
            window.removeEventListener(OPEN_EVENT, this._onOpen);
        },

        open() {
            this.chapterId = String(config.chapterId ?? '');
            this.name = '';
            this.error = '';
            this.busy = false;

            window.dispatchEvent(new CustomEvent('open-modal', { detail: DIALOG_NAME }));

            // x-modal focuses its first control after it opens. The title field must win.
            setTimeout(() => document.querySelector(config.nameSelector ?? '')?.focus(), MODAL_FOCUS_MS);
        },

        close() {
            window.dispatchEvent(new CustomEvent('close-modal', { detail: DIALOG_NAME }));
        },

        async submit() {
            if (this.busy) return;

            this.busy = true;
            this.error = '';

            let url;

            try {
                const response = await window.axios.post(config.url, { chapter_id: this.chapterId, name: this.name });

                url = response.data.url;
            } catch (error) {
                this.showFailure(error);

                return;
            }

            const store = Alpine.store('autosave');
            const clean = store ? await settleAutosaves(store) : true;

            this.close();

            if (clean) {
                this.visit(url);
            } else {
                window.dispatchEvent(new CustomEvent(ASK_EVENT, { detail: { href: url } }));
            }

            this.busy = false;
        },

        showFailure(error) {
            const response = error && error.response;

            this.error = response && response.status === 422
                ? (response.data?.message ?? config.invalidMessage)
                : config.failureMessage;

            this.busy = false;
        },

        visit(url) {
            window.location.href = url;
        },
    }));
}
