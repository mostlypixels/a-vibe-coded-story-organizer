/** The server sets `disabled` to prevent an invalid revision pair. */
export function matchesFilters(option, filters = {}) {
    const query = (filters.query ?? '').trim().toLowerCase();

    if (query !== '' && !option.label.toLowerCase().includes(query)) return false;
    if (filters.manualOnly && option.origin !== 'manual') return false;

    // ISO dates compare correctly as strings and do not introduce a time zone.
    if (filters.dateFrom && option.savedAt < filters.dateFrom) return false;
    if (filters.dateTo && option.savedAt > filters.dateTo) return false;

    return true;
}

export function visibleOptions(options, filters = {}) {
    return options.filter((option) => matchesFilters(option, filters));
}

/** Do not wrap because this can make keyboard users lose their position. */
export function nextEnabledIndex(options, from, step) {
    for (let index = from + step; index >= 0 && index < options.length; index += step) {
        if (!options[index].disabled) return index;
    }

    return from;
}

export function edgeEnabledIndex(options, edge = 'first') {
    const indexes = options.map((option, index) => (option.disabled ? -1 : index)).filter((index) => index >= 0);

    if (indexes.length === 0) return -1;

    return edge === 'first' ? indexes[0] : indexes[indexes.length - 1];
}

export function initialActiveIndex(options, selectedId) {
    const selected = options.findIndex((option) => option.id === selectedId && !option.disabled);

    return selected >= 0 ? selected : edgeEnabledIndex(options, 'first');
}

/** Write both revision IDs because the server supplies defaults for omitted IDs. */
export function selectionUrl(currentUrl, side, saveId, pair = {}) {
    const url = new URL(currentUrl);

    for (const [name, value] of Object.entries({ ...pair, [side]: saveId })) {
        if (value) url.searchParams.set(name, value);
    }

    return url.toString();
}

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
            // Keep the native select until Alpine is ready.
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

            // Return focus to the trigger after Escape.
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
            // Keep aria-activedescendant on a visible option.
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
