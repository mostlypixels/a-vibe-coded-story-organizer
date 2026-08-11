/**
 * Drag support for the tick tracks on the appearance form.
 *
 * The track is a group of native radios laid along a line. Dragging is pure
 * enhancement: with JS off, the radios are still clickable through their labels
 * and operable with arrow keys, and the form posts the same slug either way.
 * Nothing here touches CSS values — it only moves the checked radio and lets the
 * existing `change` listener do the painting.
 *
 * The handle snaps between steps rather than following the pointer: the control
 * has five positions and no in-between, so a handle sliding smoothly would
 * promise a precision that does not exist.
 */

/**
 * Pure, exported for tests: which step contains `clientX`, given the track's
 * bounding rect and how many steps it holds.
 *
 * Each step owns an equal-width slice of the track and its tick sits at the
 * slice's centre, so the slice under the pointer is also the nearest tick.
 * Positions outside the track clamp to the end steps, which is what makes a
 * drag that runs off the edge settle on the last step instead of stopping.
 */
export function stepAt(clientX, rect, count) {
    if (!(count > 0) || !(rect?.width > 0)) {
        return null;
    }

    const slice = rect.width / count;
    const index = Math.floor((clientX - rect.left) / slice);

    return Math.min(Math.max(index, 0), count - 1);
}

/**
 * Checks the radio at `index` and fires `change` so the live preview hears it.
 * Returns true when something actually moved, so a drag across a single step
 * does not re-dispatch on every pointer event.
 */
export function selectStep(radios, index) {
    const radio = radios[index];

    if (!radio || radio.checked) {
        return false;
    }

    radio.checked = true;
    radio.dispatchEvent(new Event('change', { bubbles: true }));

    return true;
}

export function registerSettingTrack(Alpine) {
    Alpine.data('settingTrack', () => ({
        dragging: false,

        // Declared here, not just assigned in `init()`: an undeclared property
        // is not part of each component's own data, so all four tracks on the
        // page end up sharing one list and every track drives the last one.
        radios: [],

        init() {
            this.radios = [...this.$el.querySelectorAll('input[type="radio"]')];
        },

        // Pointer capture keeps the events coming to this element once the drag
        // starts, so the pointer may leave the track without dropping it.
        start(event) {
            this.dragging = true;
            this.$el.setPointerCapture?.(event.pointerId);
            this.moveTo(event.clientX);
        },

        move(event) {
            if (this.dragging) {
                this.moveTo(event.clientX);
            }
        },

        stop(event) {
            this.dragging = false;
            this.$el.releasePointerCapture?.(event.pointerId);
            this.focusChecked();
        },

        moveTo(clientX) {
            const index = stepAt(clientX, this.$el.getBoundingClientRect(), this.radios.length);

            if (index !== null) {
                selectStep(this.radios, index);
            }
        },

        // `pointerdown.prevent` stops the label focusing its radio, which would
        // leave the track unfocused after a drag — so arrow keys could not
        // continue where the pointer left off without tabbing back in.
        // `preventScroll`: focusing the visually-hidden radio otherwise scrolls
        // it into view and the page lurches under the cursor. The user just
        // dragged this track, so it is on screen already.
        focusChecked() {
            this.radios.find((radio) => radio.checked)?.focus({ preventScroll: true });
        },
    }));
}
