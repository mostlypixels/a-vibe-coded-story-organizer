/**
 * The panel layout of the Write page: side panel widths and collapse, and the collapse of the
 * Codex and Notes sections.
 *
 * From `lg` up, each change is saved to the user with one PATCH, 500 ms after the last change.
 * A failed save keeps the change on screen and does not retry.
 * Below `lg`, both panels start collapsed, an open panel is a drawer, and nothing is saved.
 */

export const KEY_STEP = 16;
export const SAVE_DELAY_MS = 500;
export const LARGE_QUERY = '(min-width: 64rem)';

const SIDES = ['left', 'right'];

/** CSS also caps a panel at 40vw, so a wider value would not show. */
export function maxWidthFor(min, max, windowWidth) {
    return Math.max(min, Math.min(max, Math.floor(windowWidth * 0.4)));
}

export function clampWidth(width, min, max) {
    return Math.max(min, Math.min(max, Math.round(width)));
}

/** The new width after one key on the handle, or null for a key the handle does not use. */
export function widthAfterKey(key, side, width, min, max) {
    // The left handle is on the right edge, so ArrowRight makes the left panel wider.
    const grow = side === 'left' ? 'ArrowRight' : 'ArrowLeft';
    const shrink = side === 'left' ? 'ArrowLeft' : 'ArrowRight';

    switch (key) {
        case grow:
            return clampWidth(width + KEY_STEP, min, max);
        case shrink:
            return clampWidth(width - KEY_STEP, min, max);
        case 'Home':
            return min;
        case 'End':
            return max;
        default:
            return null;
    }
}

function matchesLarge() {
    try {
        return window.matchMedia(LARGE_QUERY).matches;
    } catch {
        return true;
    }
}

export function registerEditorLayout(Alpine) {
    Alpine.data('editorLayout', (config = {}) => ({
        left: { ...config.layout.left },
        right: { ...config.layout.right },
        sections: { ...config.layout.sections },
        large: true,
        drawer: null,
        drag: null,

        init() {
            this.large = matchesLarge();

            try {
                this._media = window.matchMedia(LARGE_QUERY);
                this._onMedia = (event) => {
                    this.large = event.matches;
                    this.drawer = null;
                };
                this._media.addEventListener('change', this._onMedia);
            } catch {
                this._media = null;
            }
        },

        destroy() {
            this._media?.removeEventListener('change', this._onMedia);
            clearTimeout(this._saveTimer);
        },

        isOpen(side) {
            return this.large ? !this[side].collapsed : this.drawer === side;
        },

        maxWidth() {
            return maxWidthFor(config.minWidth, config.maxWidth, window.innerWidth);
        },

        columns() {
            const column = (side) => (this[side].collapsed ? '2.5rem' : `${this[side].width}px`);

            return `--editor-left: ${column('left')}; --editor-right: ${column('right')};`;
        },

        toggle(side) {
            if (!this.large) {
                this.drawer = this.drawer === side ? null : side;

                return;
            }

            this[side].collapsed = !this[side].collapsed;
            this.queueSave();
        },

        closeDrawer() {
            if (this.drawer === null) return false;

            const side = this.drawer;
            this.drawer = null;
            this.$nextTick?.(() => document.querySelector(`[data-editor-collapse="${side}"]`)?.focus());

            return true;
        },

        setWidth(side, width) {
            const next = clampWidth(width, config.minWidth, this.maxWidth());

            if (next === this[side].width) return;

            this[side].width = next;
            this.queueSave();
        },

        resetWidth(side) {
            this.setWidth(side, config.defaults[side].width);
        },

        onHandleKey(side, event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.toggle(side);
                // The handle hides with the panel, so the focus moves to the expand button.
                this.$nextTick?.(() => document.querySelector(`[data-editor-collapse="${side}"]`)?.focus());

                return;
            }

            const next = widthAfterKey(event.key, side, this[side].width, config.minWidth, this.maxWidth());

            if (next === null) return;

            event.preventDefault();
            this.setWidth(side, next);
        },

        startDrag(side, event) {
            if (event.button !== undefined && event.button !== 0) return;

            event.preventDefault();
            event.currentTarget.setPointerCapture?.(event.pointerId);
            this.drag = { side, startX: event.clientX, startWidth: this[side].width };
        },

        moveDrag(event) {
            if (!this.drag) return;

            const delta = event.clientX - this.drag.startX;
            const sign = this.drag.side === 'left' ? 1 : -1;

            this.setWidth(this.drag.side, this.drag.startWidth + sign * delta);
        },

        endDrag(event) {
            if (!this.drag) return;

            event?.currentTarget?.releasePointerCapture?.(event.pointerId);
            this.drag = null;
        },

        /** From the `toggle` event of a section `<details>`. Browsers also send it at load for an open one. */
        setSection(section, open) {
            if (this.sections[section] === !open) return;

            this.sections[section] = !open;
            this.queueSave();
        },

        queueSave() {
            if (!this.large) return;

            clearTimeout(this._saveTimer);
            this._saveTimer = setTimeout(() => this.saveLayout(), config.saveDelayMs ?? SAVE_DELAY_MS);
        },

        async saveLayout() {
            const payload = { sections: { ...this.sections } };

            for (const side of SIDES) {
                payload[side] = { width: this[side].width, collapsed: this[side].collapsed };
            }

            try {
                await window.axios.patch(config.saveUrl, payload);
            } catch {
                // The layout is a convenience: the screen keeps the change, the next change tries again.
            }
        },
    }));
}
