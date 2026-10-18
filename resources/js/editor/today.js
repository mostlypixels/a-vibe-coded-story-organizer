import { formatCount } from '../word-count';

// The autosave field sends this event when the server reports the scene word count.
const RECONCILE_EVENT = 'word-count:reconcile';

/** Today at load plus the words this page added since the load. Cut text makes it negative. */
export function liveToday(todayAtLoad, countAtLoad, latestCount) {
    return todayAtLoad + (latestCount - countAtLoad);
}

/** The same rounding and limits as the `x-progress-bar` Blade component. */
export function goalPercent(value, goal) {
    if (!goal || goal <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, Math.round((value / goal) * 100)));
}

export function registerEditorToday(Alpine) {
    Alpine.data('editorToday', (config = {}) => ({
        written: config.today ?? 0,
        goal: config.goal ?? null,

        init() {
            this._onReconcile = (event) => {
                if (typeof event.detail?.wordCount === 'number') {
                    this.written = liveToday(config.today ?? 0, config.sceneCount ?? 0, event.detail.wordCount);
                }
            };
            window.addEventListener(RECONCILE_EVENT, this._onReconcile);
        },

        destroy() {
            window.removeEventListener(RECONCILE_EVENT, this._onReconcile);
        },

        writtenText() {
            return formatCount(this.written, config.templates ?? { zero: '%d', one: '%d', other: '%d' });
        },

        percent() {
            return goalPercent(this.written, this.goal);
        },
    }));
}
