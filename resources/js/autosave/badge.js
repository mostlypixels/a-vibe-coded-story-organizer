/**
 * The global lower-right autosave badge (task 9, `expanded/ui.md` "Global indicator").
 * A single, page-wide indicator reflecting the worst-state-wins outcome across every
 * `x-autosave-field` instance currently mounted on the page, via the shared
 * `Alpine.store('autosave')` that `registerAutosaveField()` (./field.js) populates.
 *
 * Deliberately additive, not a replacement: the per-field inline indicator built in
 * task 8 keeps showing each field's own precise state (`handoff.md` §9.5 "both
 * indicators, always" — `resources/views/projects/edit.blade.php` alone has 6
 * autosaving fields, so a global-only badge could never say which one needed
 * attention). This badge only answers "is anything on this page not idle right now".
 *
 * No new state/precedence logic lives here — `worstState()` and the `STATES` enum both
 * come from ./store.js, the one place per-state precedence is decided (task 7).
 */
import { STATES } from './store';

/**
 * User-facing copy per state. `session-expired` and `forbidden-after-replay` carry
 * this task's own dedicated copy (`handoff.md` §9.6, `open-questions.md` #5) — neither
 * ever clears the writer's typed text (see field.js's `save()`, which never touches
 * the editor DOM on a failed save), so "your work is safe" is literally true: the text
 * is still sitting right there in the field, selectable/copyable at any time.
 */
const BADGE_COPY = {
    [STATES.SAVING]: 'Saving…',
    [STATES.SAVED]: 'Saved',
    [STATES.RETRYING]: 'Reconnecting…',
    [STATES.CONFLICT]: 'Save conflict — needs your attention',
    [STATES.SESSION_EXPIRED]: 'Session expired — your work is safe.',
    [STATES.FORBIDDEN_AFTER_REPLAY]: "You're signed in as a different account — copy your text before switching back.",
    [STATES.ERROR]: "Couldn't save — check your connection.",
};

/** Tailwind classes per state: red/amber for anything needing a human decision or a
 *  soft retry, green for a fresh save, neutral gray while a save is in flight. */
const BADGE_STYLES = {
    [STATES.SESSION_EXPIRED]: 'border-amber-300 bg-amber-50 text-amber-800',
    [STATES.CONFLICT]: 'border-red-300 bg-red-50 text-red-800',
    [STATES.FORBIDDEN_AFTER_REPLAY]: 'border-red-300 bg-red-50 text-red-800',
    [STATES.ERROR]: 'border-red-300 bg-red-50 text-red-800',
    [STATES.RETRYING]: 'border-amber-300 bg-amber-50 text-amber-800',
    [STATES.SAVING]: 'border-gray-300 bg-white text-gray-600',
    [STATES.SAVED]: 'border-green-300 bg-green-50 text-green-700',
};

const DEFAULT_BADGE_STYLE = 'border-gray-300 bg-white text-gray-600';

/** States a click should never try to "jump to a field" for — the fix there is the
 *  Sign in link (session-expired) or manually copying text before switching accounts
 *  back (forbidden-after-replay), not scrolling to a field. */
const NON_NAVIGABLE_STATES = [STATES.SESSION_EXPIRED, STATES.FORBIDDEN_AFTER_REPLAY];

/** Pure lookups, exported separately so vitest can cover the copy/style/navigability
 *  tables without going through Alpine.data()'s reactive wrapper. */
export function labelFor(state) {
    return BADGE_COPY[state] ?? '';
}

export function classesFor(state) {
    return BADGE_STYLES[state] ?? DEFAULT_BADGE_STYLE;
}

export function isNavigable(state) {
    return !NON_NAVIGABLE_STATES.includes(state);
}

export function registerAutosaveBadge(Alpine) {
    Alpine.data('autosaveBadge', () => ({
        get state() {
            return Alpine.store('autosave').worstState();
        },

        /** Invisible at idle — no persistent chrome when nothing is happening. */
        get visible() {
            return this.state !== STATES.IDLE;
        },

        get label() {
            return labelFor(this.state);
        },

        get badgeClasses() {
            return classesFor(this.state);
        },

        get showSignIn() {
            return this.state === STATES.SESSION_EXPIRED;
        },

        /**
         * Scrolls to and focuses the first field currently sitting in the badge's own
         * (worst) state. `store.elements` is populated by `registerAutosaveField()`'s
         * `init()`/`destroy()` alongside `store.fields`, so the two stay in sync.
         */
        focusField() {
            if (!isNavigable(this.state)) {
                return;
            }

            const store = Alpine.store('autosave');
            const key = Object.keys(store.fields).find((candidate) => store.fields[candidate] === this.state);
            const element = key ? store.elements[key] : null;

            if (!element) {
                return;
            }

            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            element.querySelector('textarea, [contenteditable="true"]')?.focus();
        },
    }));
}
