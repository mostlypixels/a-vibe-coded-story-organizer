/**
 * The save-point picker — a W3C APG *select-only combobox* that progressively
 * enhances the plain `<select>` on the compare page
 *
 * A native `<select>` is the right control for a short list, and it stays the
 * no-JS baseline. It stops being enough here for one reason: the panel carries
 * *filters* (manual-only, a date range), and a `<select>` cannot hold them. A
 * bare `<div>` dropdown, meanwhile, tells a screen reader nothing at all — hence
 * the full combobox role/state contract rather than something homegrown.
 *
 * Shaped like `navigation-guard.js` and `autosave/badge.js`: the decisions are
 * pure exported functions, unit-tested below the DOM; the `Alpine.data()`
 * wrapper is the thin impure half that moves focus and navigates.
 *
 * > [!NOTE]
 * > The two pickers on a compare page are independent, and their filters are
 * > deliberately **not** synced. That is the point: finding the save that broke
 * > something means comparing one manual checkpoint against the autosaves around
 * > it, which is impossible if narrowing one side narrows the other.
 */

/**
 * One option, as the Blade component serialises a SavePoint:
 *
 *   { id, label, origin, savedAt: 'YYYY-MM-DD', isCurrent, disabled }
 *
 * `disabled` is decided server-side — the newer picker locks out everything not
 * strictly newer than the older selection, so the invalid pairing is unreachable
 * rather than validated after the fact.
 */

/**
 * Does this option survive the panel's filters?
 *
 * The text query matches the whole rendered label, so typing a date, a label, or
 * an origin all work without the reader having to know which field is which.
 */
export function matchesFilters(option, filters = {}) {
    const query = (filters.query ?? '').trim().toLowerCase();

    if (query !== '' && !option.label.toLowerCase().includes(query)) return false;
    if (filters.manualOnly && option.origin !== 'manual') return false;

    // Dates compare as ISO strings (YYYY-MM-DD), which sort lexicographically —
    // no Date parsing, no timezone to get wrong.
    if (filters.dateFrom && option.savedAt < filters.dateFrom) return false;
    if (filters.dateTo && option.savedAt > filters.dateTo) return false;

    return true;
}

/**
 * The options the panel should show, in the order it shows them.
 */
export function visibleOptions(options, filters = {}) {
    return options.filter((option) => matchesFilters(option, filters));
}

/**
 * The next option the arrow keys should land on, skipping disabled ones.
 *
 * Returns the current index when there is nowhere to go, so holding ↓ at the end
 * of the list parks rather than wrapping — wrapping in a long history loses the
 * reader's place with no way to notice.
 */
export function nextEnabledIndex(options, from, step) {
    for (let index = from + step; index >= 0 && index < options.length; index += step) {
        if (!options[index].disabled) return index;
    }

    return from;
}

/**
 * The first (or last) option that can actually be chosen — where Home/End go,
 * and where the panel puts the active option when nothing is selected yet.
 */
export function edgeEnabledIndex(options, edge = 'first') {
    const indexes = options.map((option, index) => (option.disabled ? -1 : index)).filter((index) => index >= 0);

    if (indexes.length === 0) return -1;

    return edge === 'first' ? indexes[0] : indexes[indexes.length - 1];
}

/**
 * Where the panel should open: on the current selection when it is still
 * visible under the filters, otherwise on the first option that can be chosen.
 */
export function initialActiveIndex(options, selectedId) {
    const selected = options.findIndex((option) => option.id === selectedId && !option.disabled);

    return selected >= 0 ? selected : edgeEnabledIndex(options, 'first');
}

/**
 * The URL a selection navigates to: this page, with one side of the pair
 * replaced.
 *
 * `pair` is the pair the page is *currently showing*, and both halves are always
 * written. That matters when the reader arrived without an explicit pair — the
 * compare page defaults to the two most recent save points — because writing
 * only the chosen side would leave the other one unset, and the page would
 * default it right back to where it was. The selection would appear to do
 * nothing.
 *
 * Every other query parameter is carried through untouched, so choosing a save
 * point never silently drops the `?field=` filter the reader arrived with.
 */
export function selectionUrl(currentUrl, side, saveId, pair = {}) {
    const url = new URL(currentUrl);

    for (const [name, value] of Object.entries({ ...pair, [side]: saveId })) {
        if (value) url.searchParams.set(name, value);
    }

    return url.toString();
}

/**
 * The Alpine component. Everything above is pure; this is the part that touches
 * focus and the address bar.
 */
export function registerRevisionPicker(Alpine) {
    Alpine.data('revisionPicker', ({ side, options = [], selectedId = null, labelledBy = '', pair = {} }) => ({
        ready: false,
        open: false,
        options,
        side,
        selectedId,
        labelledBy,
        pair,
        activeIndex: -1,
        query: '',
        manualOnly: false,
        dateFrom: '',
        dateTo: '',

        init() {
            // Flipped only once Alpine is actually running, which is what swaps
            // the native <select> for this. If the bundle never loads, the
            // baseline stays exactly as the server rendered it.
            this.ready = true;
        },

        get filters() {
            return {
                query: this.query,
                manualOnly: this.manualOnly,
                dateFrom: this.dateFrom,
                dateTo: this.dateTo,
            };
        },

        get visible() {
            return visibleOptions(this.options, this.filters);
        },

        get selected() {
            return this.options.find((option) => option.id === this.selectedId) ?? null;
        },

        get activeId() {
            const option = this.visible[this.activeIndex];

            return option ? `${this.side}-option-${option.id}` : null;
        },

        optionId(option) {
            return `${this.side}-option-${option.id}`;
        },

        openPanel() {
            this.open = true;
            this.activeIndex = initialActiveIndex(this.visible, this.selectedId);
            this.$nextTick(() => this.$refs.search?.focus());
        },

        closePanel({ refocus = true } = {}) {
            this.open = false;
            this.activeIndex = -1;

            // Escape must hand focus back to the trigger, or the reader is left
            // at the top of the document with no idea where they are.
            if (refocus) this.$refs.trigger?.focus();
        },

        togglePanel() {
            this.open ? this.closePanel() : this.openPanel();
        },

        move(step) {
            if (!this.open) {
                this.openPanel();

                return;
            }

            this.activeIndex = nextEnabledIndex(this.visible, this.activeIndex, step);
        },

        jump(edge) {
            this.activeIndex = edgeEnabledIndex(this.visible, edge);
        },

        onFilterChange() {
            // The active option may have just been filtered away; re-anchor
            // rather than leaving aria-activedescendant pointing at nothing.
            this.activeIndex = initialActiveIndex(this.visible, this.selectedId);
        },

        choose(option) {
            if (!option || option.disabled) return;

            window.location.assign(selectionUrl(window.location.href, this.side, option.id, this.pair));
        },

        chooseActive() {
            this.choose(this.visible[this.activeIndex]);
        },
    }));
}
