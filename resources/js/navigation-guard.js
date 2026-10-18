/** Return true only for a normal in-app navigation. */
export function shouldIntercept(event, anchor) {
    if (!anchor || !anchor.href) return false;
    if (event.defaultPrevented) return false;
    if (event.button !== 0) return false; // not a plain left-click
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false; // open-in-new-tab etc.
    if (anchor.target && anchor.target !== '_self') return false; // target=_blank
    if (anchor.hasAttribute('download')) return false;
    if (anchor.origin !== window.location.origin) return false; // external link
    if (anchor.href.split('#')[0] === window.location.href.split('#')[0]) return false; // same-page anchor

    return true;
}

export function isGuardedSaveSubmit(event) {
    if (event.defaultPrevented) return false; // handled in JS — the page won't unload

    const submitter = event.submitter;

    return !!submitter && submitter.closest?.('[data-guard-save]') !== null;
}

/**
 * Wait for every autosave, then return true when nothing is left unsaved.
 * A conflict or a network error leaves a field dirty, so the caller asks the writer.
 */
export async function settleAutosaves(store) {
    try {
        await store.flushAll();
    } catch {
        return false;
    }

    return !store.isDirty();
}

export function registerNavigationGuard(Alpine) {
    // Do not warn during a form submission that saves the data.
    let savingViaForm = false;

    Alpine.data('navigationGuard', () => ({
        pendingHref: null,

        init() {
            // Run before component click handlers that can navigate.
            this._onClick = (event) => this.handleClick(event);
            document.addEventListener('click', this._onClick, true);
        },

        destroy() {
            document.removeEventListener('click', this._onClick, true);
        },

        handleClick(event) {
            const anchor = event.target.closest('a');

            if (!shouldIntercept(event, anchor)) {
                return;
            }

            const store = Alpine.store('autosave');

            if (!store?.isDirty()) {
                return;
            }

            event.preventDefault();
            this.pendingHref = anchor.href;

            // Typing right before a click is normal. Save first; ask only when the save fails.
            return settleAutosaves(store).then((clean) => {
                if (clean) {
                    window.location.href = this.pendingHref;
                } else {
                    this.$dispatch('open-modal', 'unsaved-changes-guard');
                }
            });
        },

        confirmLeave() {
            window.location.href = this.pendingHref;
        },
    }));

    // Run after handlers that can cancel a JavaScript-only submission.
    document.addEventListener('submit', (event) => {
        if (!isGuardedSaveSubmit(event)) {
            return;
        }

        savingViaForm = true;
    });

    // Cover tab close and navigation that does not start with an in-app click.
    window.addEventListener('beforeunload', (event) => {
        if (savingViaForm || !Alpine.store('autosave')?.isDirty()) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
}
