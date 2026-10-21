/**
 * Fades scrollbars in Chrome, like the overlay scrollbars of Firefox on Windows.
 *
 * Chrome on Windows has no overlay scrollbars. The CSS hides the thumb, and this
 * module shows it on a panel for a short time after a scroll or a pointer move.
 * Firefox does not support the `::-webkit-scrollbar` selector, so it keeps its own scrollbars.
 */
export const ACTIVE_CLASS = 'scrollbar-active';
export const HIDE_DELAY = 1000;

/** Shows the thumb on an element, and hides it again after `delay` ms of no activity. */
export function createScrollbarFader({ delay = HIDE_DELAY } = {}) {
    const timers = new Map();

    return {
        show(element) {
            element.classList.add(ACTIVE_CLASS);
            clearTimeout(timers.get(element));
            timers.set(
                element,
                setTimeout(() => {
                    element.classList.remove(ACTIVE_CLASS);
                    timers.delete(element);
                }, delay),
            );
        },
    };
}

/** `start` and each ancestor whose content is larger than its box. */
export function scrollableAncestors(start) {
    const found = [];

    for (let element = start; element instanceof Element; element = element.parentElement) {
        if (element.scrollHeight > element.clientHeight || element.scrollWidth > element.clientWidth) {
            found.push(element);
        }
    }

    return found;
}

export function registerScrollbarFader(doc = document) {
    if (!globalThis.CSS?.supports?.('selector(::-webkit-scrollbar)')) return;

    const fader = createScrollbarFader();
    let frame = null;

    // A scroll event does not bubble, so only the capture phase sees every panel.
    doc.addEventListener(
        'scroll',
        (event) => fader.show(event.target === doc ? doc.documentElement : event.target),
        { capture: true, passive: true },
    );

    // One check for each frame: pointermove fires much more often than the screen paints.
    doc.addEventListener(
        'pointermove',
        (event) => {
            if (frame !== null) return;

            frame = requestAnimationFrame(() => {
                frame = null;
                scrollableAncestors(event.target).forEach((element) => fader.show(element));
            });
        },
        { passive: true },
    );
}
