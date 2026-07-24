import { afterEach, describe, expect, it } from 'vitest';
import { collectDraftEntries } from './draft-recovery';
import { storageKeyFor, writeDraft } from './field';

/**
 * Tests for `resources/js/autosave/draft-recovery.js`'s DOM-free half
 * (`collectDraftEntries()`) — task 02 of `autosave-storage-improvements`. The
 * `Alpine.data('draftRecoveryModal', ...)` wrapper itself is left to task 03's
 * manual browser-verification step, matching this feature's existing convention of
 * unit-testing only the DOM-free logic (see badge.js/badge.test.js and
 * field.js/field.test.js for the same split).
 */

/** A field's root element: the `<div>` `autosaveField()` mounts on, wrapping the
 *  real `<textarea>` `collectDraftEntries()` reads the current server value/hash
 *  from — mirrors field.test.js's `mountField()` DOM shape. */
function makeFieldElement(hash, value) {
    const root = document.createElement('div');
    const textarea = document.createElement('textarea');
    textarea.dataset.hash = hash;
    textarea.value = value;
    root.appendChild(textarea);

    return root;
}

describe('collectDraftEntries', () => {
    afterEach(() => {
        window.localStorage.clear();
    });

    it('returns only live drafts, with the correct action and compareUrl for each', () => {
        const restoreKey = storageKeyFor({ entity: 'scene', id: 1, field: 'contents' });
        const compareKey = storageKeyFor({ entity: 'scene', id: 2, field: 'contents' });
        const expiredKey = storageKeyFor({ entity: 'scene', id: 3, field: 'contents' });
        const noDraftKey = storageKeyFor({ entity: 'scene', id: 4, field: 'contents' });
        const fourHoursAndOneMs = 4 * 60 * 60 * 1000 + 1;

        // Genuinely unsaved work, still matching the base hash it was typed against.
        writeDraft(restoreKey, { value: 'unsaved edit', baseHash: 'hash-a', savedAt: Date.now() });
        // The server moved on since this draft's base hash — compare-only, never a
        // bare restore.
        writeDraft(compareKey, { value: 'stale edit', baseHash: 'old-hash', savedAt: Date.now() });
        // Old enough to be past the 4-hour TTL.
        writeDraft(expiredKey, { value: 'expired edit', baseHash: 'hash-c', savedAt: Date.now() - fourHoursAndOneMs });
        // noDraftKey intentionally has no localStorage entry at all.

        const fields = { [restoreKey]: 'idle', [compareKey]: 'idle', [expiredKey]: 'idle', [noDraftKey]: 'idle' };
        const elements = {
            [restoreKey]: makeFieldElement('hash-a', 'server value a'),
            [compareKey]: makeFieldElement('new-hash', 'server value b'),
            [expiredKey]: makeFieldElement('hash-c', 'server value c'),
            [noDraftKey]: makeFieldElement('hash-d', 'server value d'),
        };
        const compareUrls = {
            [restoreKey]: '/compare/1',
            [compareKey]: '/compare/2',
            [expiredKey]: '/compare/3',
            [noDraftKey]: '/compare/4',
        };

        const entries = collectDraftEntries(fields, elements, compareUrls);

        expect(entries).toHaveLength(2);

        expect(entries.find((entry) => entry.key === restoreKey)).toMatchObject({
            action: 'restore',
            value: 'unsaved edit',
            compareUrl: '/compare/1',
        });
        expect(entries.find((entry) => entry.key === compareKey)).toMatchObject({
            action: 'compare-only',
            value: 'stale edit',
            compareUrl: '/compare/2',
        });
    });

    it('consults isDraftExpired() before triageDraft() — an expired draft is excluded even when it would otherwise offer-restore', () => {
        const key = storageKeyFor({ entity: 'scene', id: 5, field: 'contents' });
        const fourHoursAndOneMs = 4 * 60 * 60 * 1000 + 1;

        // baseHash matches the "server" hash exactly and the value differs from the
        // server value — triageDraft() alone would say 'offer-restore' — but this
        // draft is expired, so it must never reach that check at all.
        writeDraft(key, { value: 'unsaved edit', baseHash: 'matching-hash', savedAt: Date.now() - fourHoursAndOneMs });

        const fields = { [key]: 'idle' };
        const elements = { [key]: makeFieldElement('matching-hash', 'server value') };
        const compareUrls = { [key]: '/compare/5' };

        expect(collectDraftEntries(fields, elements, compareUrls)).toHaveLength(0);
    });

    it('drops a draft whose value now equals the server value (it landed or was undone)', () => {
        const key = storageKeyFor({ entity: 'scene', id: 6, field: 'contents' });

        // A live, non-expired draft — but its value matches the current server value,
        // so triageDraft() returns 'drop-silently' and collectDraftEntries() must skip
        // it rather than offering a recovery for work that is no different from what is
        // already saved.
        writeDraft(key, { value: 'same as server', baseHash: 'hash-a', savedAt: Date.now() });

        const fields = { [key]: 'idle' };
        const elements = { [key]: makeFieldElement('hash-a', 'same as server') };
        const compareUrls = { [key]: '/compare/6' };

        expect(collectDraftEntries(fields, elements, compareUrls)).toHaveLength(0);
    });

    it('skips a registered field whose element has no textarea to compare against', () => {
        const key = storageKeyFor({ entity: 'scene', id: 7, field: 'contents' });

        writeDraft(key, { value: 'unsaved edit', baseHash: 'hash-a', savedAt: Date.now() });

        // A stale/mismatched map entry: the element exists but holds no <textarea>, so
        // there is no live server value/hash to triage against — the field is skipped
        // rather than throwing while building the recovery list.
        const fields = { [key]: 'idle' };
        const elements = { [key]: document.createElement('div') };
        const compareUrls = { [key]: '/compare/7' };

        expect(collectDraftEntries(fields, elements, compareUrls)).toHaveLength(0);
    });

    it('returns an empty list when nothing is registered', () => {
        expect(collectDraftEntries({}, {}, {})).toEqual([]);
    });
});
