/**
 * Global in-app navigation guard + native `beforeunload` fallback (data-loss-warnings
 * task 02, `.specs/planned/2026-07/data-loss-warnings/expanded/architecture.md` §2-3).
 *
 * Mirrors `resources/js/autosave/badge.js`'s shape: a pure/testable predicate
 * (`shouldIntercept`) plus an `Alpine.data()` wrapper for the impure DOM half. Both the
 * in-app guard and the `beforeunload` fallback read the single `Alpine.store('autosave')
 * .isDirty()` signal task 01 added — no separate "is this page dirty" tracking here.
 *
 * V1 scope (00-overview.md's binding decision #5): only pages with `x-autosave-field`
 * instances are covered. A page with zero autosave fields never has `isDirty() ===
 * true`, so this guard is a correctly-behaving no-op there — no per-page opt-in needed.
 */

/**
 * Pure predicate: should this click be intercepted for the unsaved-changes dialog?
 * Takes the real DOM `click` event plus the closest `<a href>` ancestor (or `null`).
 * Returns `false` for every case the browser's default handling must proceed
 * untouched (opening in a new tab, downloads, external/hash links, etc.) — deliberately
 * exhaustive rather than a blanket "intercept everything".
 */
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

/**
 * Pure predicate: is this `submit` event the entity edit page's **Save / Save and stay**
 * action — i.e. triggered by a button carrying `data-guard-save` (see
 * `resources/views/components/edit-actions.blade.php`)? Those are the deliberate,
 * data-persisting submits whose full-page navigation must not raise the unsaved-changes
 * prompt: the submit *is* the save.
 *
 * Every other form submit on the page (search, logout, delete, JS-only forms) is
 * deliberately left untouched, so it keeps warning/behaving exactly as before. Read in
 * the bubbling phase (see `registerNavigationGuard`) so a handler that already cancelled
 * the submit is honored via `event.defaultPrevented`.
 */
export function isGuardedSaveSubmit(event) {
    if (event.defaultPrevented) return false; // handled in JS — the page won't unload

    const submitter = event.submitter;

    return !!submitter && submitter.closest?.('[data-guard-save]') !== null;
}

export function registerNavigationGuard(Alpine) {
    // Set once a Save / Save and stay submit is in flight (see edit-actions.blade.php's
    // `data-guard-save`). The page is already committing to that navigation, so the
    // native `beforeunload` fallback below must not second-guess it with an unsaved-
    // changes prompt. Never reset — like field.js's `explicitLeavePending`, a full-page
    // submit unloads this document, and "Save and stay" reloads a fresh module, so there
    // is no later `beforeunload` on this same page instance to wrongly suppress.
    let savingViaForm = false;

    Alpine.data('navigationGuard', () => ({
        pendingHref: null,

        init() {
            // Capturing phase so this runs before any per-component @click handler
            // that might itself navigate (architecture.md §2).
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

            if (!Alpine.store('autosave')?.isDirty()) {
                return;
            }

            event.preventDefault();
            this.pendingHref = anchor.href;
            this.$dispatch('open-modal', 'unsaved-changes-guard');
        },

        /**
         * Leave button: fire the sibling `autosave-storage-improvements` integration
         * event synchronously, before navigation starts, then navigate. Cancel/Esc/
         * backdrop-click never call this — `<x-modal>`'s existing `close` handling
         * covers that with no extra code (nothing here to explicitly reset
         * `pendingHref` on cancel, it's simply never read).
         */
        confirmLeave() {
            window.dispatchEvent(new CustomEvent('autosave:explicit-leave'));
            window.location.href = this.pendingHref;
        },
    }));

    // The Save / Save and stay buttons persist the writer's data, so their submit must
    // never raise the unsaved-changes prompt. Bubbling phase so a JS-only form that
    // `preventDefault()`s its submit has already done so (and won't unload). We also fire
    // `autosave:explicit-leave` so each field skips its now-redundant `beforeunload`
    // draft mirror (field.js) — the submit is itself the save.
    document.addEventListener('submit', (event) => {
        if (!isGuardedSaveSubmit(event)) {
            return;
        }

        savingViaForm = true;
        window.dispatchEvent(new CustomEvent('autosave:explicit-leave'));
    });

    // Native fallback for tab-close/hard navigation, where there is no in-app click to
    // intercept. Deliberately dumb (architecture.md §3): no custom text (browsers
    // ignore it), and no attempt to distinguish which button the user eventually picks
    // in the native prompt — `autosave:explicit-leave` can never fire from here. Stays
    // silent once a Save / Save and stay submit is in flight.
    window.addEventListener('beforeunload', (event) => {
        if (savingViaForm || !Alpine.store('autosave')?.isDirty()) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
}
