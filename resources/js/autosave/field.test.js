import { afterEach, describe, expect, it } from 'vitest';
import { clearDraft, readDraft, shouldAutosave, storageKeyFor, writeDraft } from './field';

/**
 * Covers task 08's DOM-free logic: the localStorage key-building for both the
 * existing-entity and `new:` create-form shapes, the dirty-only gating function,
 * and the localStorage draft mirror itself. Everything requiring a real Alpine
 * mount (debounce timers wired to DOM events, the axios round-trip) is left to
 * the manual checklist per handoff.md §9.12 / task 08's own scope, matching
 * wysiwyg.test.js's precedent of only unit-testing the DOM-free logic.
 */
describe('storageKeyFor', () => {
    it('keys an existing entity as entity:id:field', () => {
        expect(storageKeyFor({ entity: 'scene', id: 42, field: 'contents' })).toBe('scene:42:contents');
    });

    it('keys a create form as new:entity:parentId:field, with no id required', () => {
        expect(storageKeyFor({ entity: 'scene', id: null, parentId: 7, field: 'contents' })).toBe(
            'new:scene:7:contents',
        );
    });

    it('never collides two create forms for different parents', () => {
        const first = storageKeyFor({ entity: 'scene', id: undefined, parentId: 1, field: 'contents' });
        const second = storageKeyFor({ entity: 'scene', id: undefined, parentId: 2, field: 'contents' });

        expect(first).not.toBe(second);
    });
});

describe('shouldAutosave', () => {
    it('is false until the field has actually been edited', () => {
        expect(shouldAutosave(false, 42)).toBe(false);
    });

    it('is false on a create form even after an edit, since there is no id to PATCH', () => {
        expect(shouldAutosave(true, null)).toBe(false);
        expect(shouldAutosave(true, undefined)).toBe(false);
    });

    it('is true only once the field is dirty and belongs to an existing entity', () => {
        expect(shouldAutosave(true, 42)).toBe(true);
    });
});

describe('draft mirror (readDraft/writeDraft/clearDraft)', () => {
    afterEach(() => {
        window.localStorage.clear();
    });

    it('round-trips a draft written to localStorage', () => {
        writeDraft('scene:1:contents', { value: 'Hello', baseHash: 'abc', savedAt: 123 });

        expect(readDraft('scene:1:contents')).toEqual({ value: 'Hello', baseHash: 'abc', savedAt: 123 });
    });

    it('returns null for a key that was never written', () => {
        expect(readDraft('scene:404:contents')).toBeNull();
    });

    it('returns null instead of throwing on corrupt JSON', () => {
        window.localStorage.setItem('scene:1:contents', '{not json');

        expect(readDraft('scene:1:contents')).toBeNull();
    });

    it('clears a draft', () => {
        writeDraft('scene:1:contents', { value: 'Hello', baseHash: 'abc', savedAt: 123 });
        clearDraft('scene:1:contents');

        expect(readDraft('scene:1:contents')).toBeNull();
    });

    // The quota-exceeded eviction path (handoff.md §9.7: "evict oldest-first on
    // QuotaExceededError") is exercised by the manual checklist (testing.md), not
    // here: jsdom's Storage implementation doesn't allow reliably stubbing
    // setItem() to simulate QuotaExceededError from a unit test, so faking it
    // would test the mock, not the browser behavior it's standing in for.
});
