// The pointer needs time to cross the gap between the trigger and the panel.
export const HOVER_CLOSE_DELAY = 250;

// Only one dropdown is open at a time. Each one closes when another announces this event.
export const OPEN_EVENT = 'dropdown-opened';

/** Touch and pen taps also fire pointer events. Only a mouse hovers. */
export function isMouse(event) {
    return event?.pointerType === 'mouse';
}

/**
 * Behaviour of x-dropdown. The `open` name is part of the contract: x-disclosure-button
 * and the toolbar menu button read it for `aria-expanded`.
 */
export function registerDropdown(Alpine) {
    Alpine.data('dropdown', ({ id, hover = false } = {}) => ({
        open: false,
        // A click opened the menu. The pointer no longer closes it.
        pinned: false,
        closeTimer: null,

        show() {
            this.cancelClose();

            if (this.open) return;

            this.open = true;
            window.dispatchEvent(new CustomEvent(OPEN_EVENT, { detail: id }));
        },

        close() {
            this.cancelClose();
            this.open = false;
            this.pinned = false;
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        // In hover mode a link can sit in the trigger. Only the disclosure button toggles, so the link navigates.
        triggerClick(event) {
            if (!hover) return this.toggle();

            if (!event.target.closest(`[aria-controls="${id}"]`)) return;

            // The chevron points up while the menu is open, so a click closes it, also after a hover.
            this.toggle();
            this.pinned = this.open;
        },

        otherOpened(event) {
            if (event.detail !== id) this.close();
        },

        pointerEnter(event) {
            if (hover && isMouse(event)) this.show();
        },

        pointerLeave(event) {
            if (!hover || !isMouse(event) || !this.open || this.pinned) return;

            this.cancelClose();
            this.closeTimer = setTimeout(() => this.close(), HOVER_CLOSE_DELAY);
        },

        cancelClose() {
            clearTimeout(this.closeTimer);
            this.closeTimer = null;
        },

        // Focus is in the dropdown. Stop the key, so an x-modal around it stays open.
        escape(event) {
            if (!this.open) return;

            event.stopPropagation();
            this.close();
            this.$root.querySelector(`[aria-controls="${id}"]`)?.focus();
        },

        // Focus is elsewhere, for example a menu opened by hover. Do not move the focus.
        escapeOutside() {
            if (this.open) this.close();
        },

        destroy() {
            this.cancelClose();
        },
    }));
}
