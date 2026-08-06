import { describe, expect, it } from 'vitest';
import { STATES } from './store.js';
import { classesFor, isNavigable, labelFor } from './badge.js';

/**
 * Tests for `resources/js/autosave/badge.js` — the global lower-right autosave
 * badge's pure lookups. The Alpine.data() wrapper itself (reading
 * `Alpine.store('autosave')`, scrolling/focusing a real DOM element) is left to
 * the manual checklist, matching this feature's existing convention of unit-testing
 * only the DOM-free logic (see field.js/field.test.js for the
 * same split).
 */

describe('labelFor', () => {
    it('gives every non-idle state its own dedicated copy', () => {
        expect(labelFor(STATES.SAVING)).toBe('Saving…');
        expect(labelFor(STATES.SAVED)).toBe('Saved');
        expect(labelFor(STATES.RETRYING)).toBe('Reconnecting…');
        expect(labelFor(STATES.CONFLICT)).toBe('Save conflict — needs your attention');
        expect(labelFor(STATES.ERROR)).toBe("Couldn't save — check your connection.");
    });

    it('session-expired reassures the writer their work is safe', () => {
        expect(labelFor(STATES.SESSION_EXPIRED)).toBe('Session expired — your work is safe.');
    });

    it('forbidden-after-replay names the account switch and tells the writer to copy their text', () => {
        expect(labelFor(STATES.FORBIDDEN_AFTER_REPLAY)).toBe(
            "You're signed in as a different account — copy your text before switching back.",
        );
    });

    it('idle (and any unrecognized state) has no label — the badge is hidden then anyway', () => {
        expect(labelFor(STATES.IDLE)).toBe('');
        expect(labelFor('made-up-state')).toBe('');
    });
});

describe('classesFor', () => {
    it('marks the states that need a human decision with the danger token', () => {
        expect(classesFor(STATES.CONFLICT)).toContain('danger');
        expect(classesFor(STATES.FORBIDDEN_AFTER_REPLAY)).toContain('danger');
        expect(classesFor(STATES.ERROR)).toContain('danger');
    });

    it('marks the soft in-progress states with the warning token, and a fresh save with success', () => {
        expect(classesFor(STATES.SESSION_EXPIRED)).toContain('warning');
        expect(classesFor(STATES.RETRYING)).toContain('warning');
        expect(classesFor(STATES.SAVED)).toContain('success');
    });

    it('falls back to a neutral style for an unrecognized state', () => {
        expect(classesFor('made-up-state')).toBe('border-border-strong bg-surface-raised text-content-muted');
    });
});

describe('isNavigable', () => {
    it('session-expired and forbidden-after-replay are never navigable — the fix there is Sign in or copying text, not scrolling to a field', () => {
        expect(isNavigable(STATES.SESSION_EXPIRED)).toBe(false);
        expect(isNavigable(STATES.FORBIDDEN_AFTER_REPLAY)).toBe(false);
    });

    it('every other non-idle state can be scrolled to and focused', () => {
        expect(isNavigable(STATES.SAVING)).toBe(true);
        expect(isNavigable(STATES.SAVED)).toBe(true);
        expect(isNavigable(STATES.RETRYING)).toBe(true);
        expect(isNavigable(STATES.CONFLICT)).toBe(true);
        expect(isNavigable(STATES.ERROR)).toBe(true);
    });
});
