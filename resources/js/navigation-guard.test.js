import { describe, expect, it } from 'vitest';
import { isGuardedSaveSubmit, shouldIntercept } from './navigation-guard';

/**
 * Tests for `resources/js/navigation-guard.js`'s pure predicate, `shouldIntercept()`
 * The `Alpine.data()` wrapper itself (the real
 * document-level click listener, `beforeunload`, `window.location` navigation) is left
 * to the manual checklist documented in `testing.md`, matching this codebase's existing
 * pure-logic-vs-manual-checklist split (see badge.js/badge.test.js).
 *
 * A fake `Location`-shaped object stands in for `window.location`/anchor properties —
 * jsdom's real `window.location` can't be reassigned per test, and these assertions
 * only need `origin`/`href`, not a full navigation.
 */
function makeAnchor(overrides = {}) {
    return {
        href: `${window.location.origin}/scenes/1`,
        origin: window.location.origin,
        target: '',
        hasAttribute: (name) => overrides.attributes?.includes(name) ?? false,
        ...overrides,
    };
}

function makeEvent(overrides = {}) {
    return {
        defaultPrevented: false,
        button: 0,
        metaKey: false,
        ctrlKey: false,
        shiftKey: false,
        altKey: false,
        ...overrides,
    };
}

describe('shouldIntercept', () => {
    it('intercepts a plain left-click, same-origin, no modifiers, no target', () => {
        window.history.pushState({}, '', '/current-page');
        expect(shouldIntercept(makeEvent(), makeAnchor())).toBe(true);
    });

    it('never intercepts when there is no anchor', () => {
        expect(shouldIntercept(makeEvent(), null)).toBe(false);
    });

    it('never intercepts when the event is already defaultPrevented', () => {
        expect(shouldIntercept(makeEvent({ defaultPrevented: true }), makeAnchor())).toBe(false);
    });

    it('never intercepts a middle or right click', () => {
        expect(shouldIntercept(makeEvent({ button: 1 }), makeAnchor())).toBe(false);
        expect(shouldIntercept(makeEvent({ button: 2 }), makeAnchor())).toBe(false);
    });

    it.each(['metaKey', 'ctrlKey', 'shiftKey', 'altKey'])('never intercepts with %s held (open-in-new-tab etc.)', (modifier) => {
        expect(shouldIntercept(makeEvent({ [modifier]: true }), makeAnchor())).toBe(false);
    });

    it('never intercepts a target="_blank" link', () => {
        expect(shouldIntercept(makeEvent(), makeAnchor({ target: '_blank' }))).toBe(false);
    });

    it('never intercepts a download link', () => {
        expect(shouldIntercept(makeEvent(), makeAnchor({ attributes: ['download'] }))).toBe(false);
    });

    it('never intercepts a cross-origin link', () => {
        expect(shouldIntercept(makeEvent(), makeAnchor({ origin: 'https://other.test', href: 'https://other.test/x' }))).toBe(false);
    });

    it('never intercepts a same-page hash-only link', () => {
        window.history.pushState({}, '', '/current-page');
        expect(
            shouldIntercept(makeEvent(), makeAnchor({ href: `${window.location.href}#section` })),
        ).toBe(false);
    });
});

/**
 * Tests for `isGuardedSaveSubmit()` — the predicate that keeps the native `beforeunload`
 * unsaved-changes prompt silent for the entity edit page's Save / Save and stay submit,
 * and only that submit. A `submitter`-shaped stand-in whose `closest()` reports whether
 * the button (or an ancestor) carries `data-guard-save` is all the predicate reads.
 */
function makeSaveSubmitter() {
    return { closest: (selector) => (selector === '[data-guard-save]' ? {} : null) };
}

function makePlainSubmitter() {
    return { closest: () => null };
}

describe('isGuardedSaveSubmit', () => {
    it('suppresses for a submit fired by a data-guard-save button (Save / Save and stay)', () => {
        expect(isGuardedSaveSubmit(makeEvent({ submitter: makeSaveSubmitter() }))).toBe(true);
    });

    it('does not suppress a submit from an unmarked button (search, logout, delete)', () => {
        expect(isGuardedSaveSubmit(makeEvent({ submitter: makePlainSubmitter() }))).toBe(false);
    });

    it('does not suppress a programmatic submit with no submitter', () => {
        expect(isGuardedSaveSubmit(makeEvent({ submitter: null }))).toBe(false);
    });

    it('does not suppress once a JS handler has preventDefault()ed the submit', () => {
        expect(isGuardedSaveSubmit(makeEvent({ defaultPrevented: true, submitter: makeSaveSubmitter() }))).toBe(false);
    });
});
