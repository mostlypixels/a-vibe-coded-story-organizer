/** Return the nearest step and clamp positions outside the track. */
export function stepAt(clientX, rect, count) {
    if (!(count > 0) || !(rect?.width > 0)) {
        return null;
    }

    const slice = rect.width / count;
    const index = Math.floor((clientX - rect.left) / slice);

    return Math.min(Math.max(index, 0), count - 1);
}

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

        // Each track must own its radio list.
        radios: [],

        init() {
            this.radios = [...this.$el.querySelectorAll('input[type="radio"]')];
        },

        // Continue the drag when the pointer leaves the track.
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

        // Restore keyboard control without scrolling to the hidden radio.
        focusChecked() {
            this.radios.find((radio) => radio.checked)?.focus({ preventScroll: true });
        },
    }));
}
