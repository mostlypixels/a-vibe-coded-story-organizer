/**
 * Keeps the scroll position and the cursor of one editor per scene in `localStorage`.
 *
 * The value is per browser on purpose: screen size and window differ between computers.
 * Storage is a convenience only, so every access can fail and the editor still works.
 */

export const SAVE_DELAY_MS = 250;

/** Null when storage is blocked: some private windows throw on the property access. */
export function browserStorage() {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

/** The stored `{scrollTop, from, to}`, or null when it is absent, broken or unreadable. */
export function readPosition(storage, key) {
    try {
        const value = JSON.parse(storage?.getItem(key) ?? 'null');

        if (value && [value.scrollTop, value.from, value.to].every(Number.isFinite)) {
            return { scrollTop: value.scrollTop, from: value.from, to: value.to };
        }
    } catch {
        // A broken or blocked value is the same as no value.
    }

    return null;
}

export function writePosition(storage, key, position) {
    try {
        storage?.setItem(key, JSON.stringify(position));
    } catch {
        // A full or blocked storage loses the position, not the text.
    }
}

/**
 * Keep the cursor inside the text of the document.
 *
 * Text positions go from 1 to `size - 1`. Position 0 and `size` are outside the first and last block.
 */
export function clampSelection({ from, to }, size) {
    const max = Math.max(1, size - 1);
    const clamp = (value) => Math.min(max, Math.max(1, Math.round(value)));
    const start = clamp(Math.min(from, to));

    return { from: start, to: Math.max(start, clamp(Math.max(from, to))) };
}

/**
 * `saved` is read at once, so the caller can skip its own focus call when a position exists.
 * Call `attach()` after the editor is visible, or the scroll box is too short to scroll.
 */
export function createPositionTracker({ key, scroller = null, storage = browserStorage() }) {
    const saved = readPosition(storage, key);
    let editor = null;
    let timer = null;

    const save = () => {
        clearTimeout(timer);
        timer = null;

        if (!editor || editor.isDestroyed) {
            return;
        }

        const { from, to } = editor.state.selection;
        writePosition(storage, key, { scrollTop: scroller?.scrollTop ?? 0, from, to });
    };

    // A throttle: the first change starts the delay, and later changes join that save.
    const schedule = () => {
        if (timer === null) {
            timer = setTimeout(save, SAVE_DELAY_MS);
        }
    };

    return {
        saved,

        attach(instance) {
            editor = instance;

            if (saved) {
                const selection = clampSelection(saved, editor.state.doc.content.size);
                editor.commands.setTextSelection(selection);
                // The stored scroll position wins, so the focus must not scroll to the cursor.
                editor.commands.focus(null, { scrollIntoView: false });

                if (scroller) {
                    scroller.scrollTop = saved.scrollTop;
                }
            }

            scroller?.addEventListener('scroll', schedule, { passive: true });
            editor.on('selectionUpdate', schedule);
            window.addEventListener('pagehide', save);
        },

        destroy() {
            if (timer !== null) {
                save();
            }

            scroller?.removeEventListener('scroll', schedule);
            editor?.off('selectionUpdate', schedule);
            window.removeEventListener('pagehide', save);
            editor = null;
        },
    };
}
