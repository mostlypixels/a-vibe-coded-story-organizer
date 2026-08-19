import { describe, expect, it } from 'vitest';
import {
    edgeEnabledIndex,
    initialActiveIndex,
    matchesFilters,
    nextEnabledIndex,
    selectionUrl,
    visibleOptions,
} from './revision-picker';

function option(overrides = {}) {
    return {
        id: '01ABC',
        label: '#3 · 24 Jul 10:43 · Saved 24 July 10:43 · Manual',
        origin: 'manual',
        savedAt: '2026-07-24',
        isCurrent: false,
        disabled: false,
        ...overrides,
    };
}

function listWithGap() {
    return [
        option({ id: 'a', label: 'newest', savedAt: '2026-07-25' }),
        option({ id: 'b', label: 'middle', savedAt: '2026-07-24', disabled: true }),
        option({ id: 'c', label: 'oldest', savedAt: '2026-07-23' }),
    ];
}

describe('matchesFilters', () => {
    it('matches anywhere in the rendered label, case-insensitively', () => {
        // One query over the whole label, so typing a date, a label or an origin
        // all work without knowing which field is which.
        expect(matchesFilters(option(), { query: 'saved 24 july' })).toBe(true);
        expect(matchesFilters(option(), { query: 'manual' })).toBe(true);
        expect(matchesFilters(option(), { query: 'nothing like this' })).toBe(false);
    });

    it('ignores an empty or whitespace-only query', () => {
        expect(matchesFilters(option(), { query: '   ' })).toBe(true);
        expect(matchesFilters(option(), {})).toBe(true);
    });

    it('narrows to manual saves when asked', () => {
        expect(matchesFilters(option({ origin: 'manual' }), { manualOnly: true })).toBe(true);
        expect(matchesFilters(option({ origin: 'automatic' }), { manualOnly: true })).toBe(false);
        expect(matchesFilters(option({ origin: 'automatic' }), { manualOnly: false })).toBe(true);
    });

    it('bounds by date inclusively at both ends', () => {
        const subject = option({ savedAt: '2026-07-24' });

        expect(matchesFilters(subject, { dateFrom: '2026-07-24' })).toBe(true);
        expect(matchesFilters(subject, { dateTo: '2026-07-24' })).toBe(true);
        expect(matchesFilters(subject, { dateFrom: '2026-07-25' })).toBe(false);
        expect(matchesFilters(subject, { dateTo: '2026-07-23' })).toBe(false);
    });

    it('combines every filter', () => {
        const subject = option({ origin: 'automatic', savedAt: '2026-07-24', label: 'an autosave' });

        expect(matchesFilters(subject, { query: 'autosave', dateFrom: '2026-07-01' })).toBe(true);
        expect(matchesFilters(subject, { query: 'autosave', manualOnly: true })).toBe(false);
    });
});

describe('visibleOptions', () => {
    it('keeps the list order', () => {
        const visible = visibleOptions(listWithGap(), { dateFrom: '2026-07-24' });

        expect(visible.map((entry) => entry.id)).toEqual(['a', 'b']);
    });

    it('can narrow to nothing', () => {
        expect(visibleOptions(listWithGap(), { query: 'no such save' })).toEqual([]);
    });
});

describe('nextEnabledIndex', () => {
    it('skips disabled options', () => {
        // The middle option is locked out because it is not newer than the
        // older selection; the arrow keys must step straight over it.
        expect(nextEnabledIndex(listWithGap(), 0, 1)).toBe(2);
        expect(nextEnabledIndex(listWithGap(), 2, -1)).toBe(0);
    });

    it('parks at the ends rather than wrapping', () => {
        // Wrapping in a long history silently loses the reader's place.
        expect(nextEnabledIndex(listWithGap(), 2, 1)).toBe(2);
        expect(nextEnabledIndex(listWithGap(), 0, -1)).toBe(0);
    });

    it('stays put when every remaining option is disabled', () => {
        const options = [option({ id: 'a' }), option({ id: 'b', disabled: true })];

        expect(nextEnabledIndex(options, 0, 1)).toBe(0);
    });
});

describe('edgeEnabledIndex', () => {
    it('finds the first and last option that can actually be chosen', () => {
        const options = [
            option({ id: 'a', disabled: true }),
            option({ id: 'b' }),
            option({ id: 'c' }),
            option({ id: 'd', disabled: true }),
        ];

        expect(edgeEnabledIndex(options, 'first')).toBe(1);
        expect(edgeEnabledIndex(options, 'last')).toBe(2);
    });

    it('reports -1 when nothing is selectable', () => {
        expect(edgeEnabledIndex([option({ disabled: true })], 'first')).toBe(-1);
        expect(edgeEnabledIndex([], 'first')).toBe(-1);
    });
});

describe('initialActiveIndex', () => {
    it('opens on the current selection', () => {
        expect(initialActiveIndex(listWithGap(), 'c')).toBe(2);
    });

    it('falls back to the first selectable option when the selection was filtered away', () => {
        // aria-activedescendant must never point at an option that is not there.
        expect(initialActiveIndex(listWithGap(), 'not-in-this-list')).toBe(0);
    });

    it('does not open on a disabled option even when it is the selection', () => {
        expect(initialActiveIndex(listWithGap(), 'b')).toBe(0);
    });
});

describe('selectionUrl', () => {
    it('replaces only its own side of the pair', () => {
        const url = selectionUrl('https://app.test/revisions/act/1/compare?from=OLD&to=NEW', 'from', 'CHOSEN');

        expect(url).toContain('from=CHOSEN');
        expect(url).toContain('to=NEW');
    });

    it('carries every other parameter through untouched', () => {
        // Choosing a save must never silently drop the ?field= filter the
        // reader arrived with.
        const url = selectionUrl('https://app.test/revisions/scene/2/compare?field=contents&from=A&to=B', 'to', 'C');

        expect(url).toContain('field=contents');
        expect(url).toContain('from=A');
        expect(url).toContain('to=C');
    });

    it('adds the parameter when the URL has none yet', () => {
        expect(selectionUrl('https://app.test/revisions/act/1/compare', 'from', 'X')).toContain('?from=X');
    });

    it('writes the other side too, so a defaulted pair does not snap back', () => {
        // A reader who arrived at /compare with no query string is looking at
        // the defaulted two most recent points. Writing only the chosen side
        // would leave the other unset and the page would default it right back,
        // making the selection look like it did nothing.
        const url = selectionUrl(
            'https://app.test/revisions/act/1/compare',
            'from',
            'OLDER',
            { from: 'WAS_OLDER', to: 'WAS_NEWER' },
        );

        expect(url).toContain('from=OLDER');
        expect(url).toContain('to=WAS_NEWER');
    });

    it('lets the chosen side win over the pair it was given', () => {
        const url = selectionUrl('https://app.test/c', 'to', 'CHOSEN', { from: 'A', to: 'B' });

        expect(url).toContain('to=CHOSEN');
        expect(url).toContain('from=A');
        expect(url).not.toContain('to=B');
    });
});

describe('the two sides are independent', () => {
    it('filtering one side leaves the other list untouched', () => {
        // The whole point: finding the save that broke something means comparing
        // one manual checkpoint against the autosaves around it, which is
        // impossible if narrowing one side narrows the other.
        const options = [
            option({ id: 'a', origin: 'manual' }),
            option({ id: 'b', origin: 'automatic' }),
        ];

        const olderSide = visibleOptions(options, { manualOnly: true });
        const newerSide = visibleOptions(options, {});

        expect(olderSide.map((entry) => entry.id)).toEqual(['a']);
        expect(newerSide.map((entry) => entry.id)).toEqual(['a', 'b']);
    });
});
