import { STATES } from './store';

const BADGE_COPY = {
    [STATES.SAVING]: 'Saving…',
    [STATES.SAVED]: 'Saved',
    [STATES.RETRYING]: 'Reconnecting…',
    [STATES.CONFLICT]: 'Save conflict — needs your attention',
    [STATES.SESSION_EXPIRED]: 'Session expired — your work is safe.',
    [STATES.FORBIDDEN_AFTER_REPLAY]: "You're signed in as a different account — copy your text before switching back.",
    [STATES.ERROR]: "Couldn't save — check your connection.",
};

const BADGE_STYLES = {
    [STATES.SESSION_EXPIRED]: 'border-warning bg-warning-surface text-warning-surface-content',
    [STATES.CONFLICT]: 'border-danger bg-danger-surface text-danger-surface-content',
    [STATES.FORBIDDEN_AFTER_REPLAY]: 'border-danger bg-danger-surface text-danger-surface-content',
    [STATES.ERROR]: 'border-danger bg-danger-surface text-danger-surface-content',
    [STATES.RETRYING]: 'border-warning bg-warning-surface text-warning-surface-content',
    [STATES.SAVING]: 'border-border-strong bg-surface-raised text-content-muted',
    [STATES.SAVED]: 'border-success bg-success-surface text-success-surface-content',
};

const DEFAULT_BADGE_STYLE = 'border-border-strong bg-surface-raised text-content-muted';

/** These states need account action instead of field action. */
const NON_NAVIGABLE_STATES = [STATES.SESSION_EXPIRED, STATES.FORBIDDEN_AFTER_REPLAY];

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
