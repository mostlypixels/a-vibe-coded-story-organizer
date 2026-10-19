import { describe, expect, it, vi } from 'vitest';
import { ASK_EVENT, isGuardedSaveSubmit, registerNavigationGuard, settleAutosaves, shouldIntercept } from './navigation-guard';

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

function makeStore({ flush = () => Promise.resolve(), dirtyAfter = false } = {}) {
    let dirty = true;

    return {
        flushAll: () => flush().then(() => { dirty = dirtyAfter; }),
        isDirty: () => dirty,
    };
}

describe('settleAutosaves', () => {
    it('is clean once every pending save lands', async () => {
        await expect(settleAutosaves(makeStore())).resolves.toBe(true);
    });

    it('is not clean when a field stays dirty (a conflict)', async () => {
        await expect(settleAutosaves(makeStore({ dirtyAfter: true }))).resolves.toBe(false);
    });

    it('is not clean when a save throws (a network error)', async () => {
        await expect(settleAutosaves(makeStore({ flush: () => Promise.reject(new Error('offline')) }))).resolves.toBe(false);
    });
});

describe('navigationGuard ask event', () => {
    it('opens the guard modal for a script that leaves the page', () => {
        let factory = null;
        registerNavigationGuard({ data: (name, make) => { factory = make; }, store: () => null });

        const guard = factory();
        guard.$dispatch = vi.fn();
        guard.init();

        window.dispatchEvent(new CustomEvent(ASK_EVENT, { detail: { href: '/scenes/42/write' } }));
        guard.destroy();

        expect(guard.pendingHref).toBe('/scenes/42/write');
        expect(guard.$dispatch).toHaveBeenCalledWith('open-modal', 'unsaved-changes-guard');
    });
});
