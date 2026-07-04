@props([
    'name',
    'options' => [],
    'selected' => [],
    'allowFreeText' => false,
    'placeholder' => '',
])

@php
    // Generic searchable chip multi-select. Options are embedded as JSON and filtered
    // client-side by Alpine (fine for hundreds of rows; a server-side search would be the
    // next step at thousands — the same tradeoff documented for x-event-picker, which this
    // component generalizes). Each selected value submits as a hidden {{ $name }}[] input,
    // matching the plain array the controller/validation already expect.
    //
    // Each option is normalized to ['value' => string, 'label' => string, 'sublabel' => ?string,
    // 'search' => string]. When $allowFreeText is true the user can also add arbitrary values not
    // present in $options (used for new tag names, which the controller firstOrCreate's).
    $normalizedOptions = collect($options)->map(fn ($option) => [
        'value' => (string) $option['value'],
        'label' => (string) $option['label'],
        'sublabel' => isset($option['sublabel']) && $option['sublabel'] !== null ? (string) $option['sublabel'] : null,
        'search' => (string) ($option['search'] ?? strtolower((string) $option['label'])),
    ])->values()->all();

    $selectedValues = collect($selected)->map(fn ($value) => (string) $value)->values()->all();
@endphp

<div
    x-data="{
        query: '',
        options: @js($normalizedOptions),
        selectedValues: @js($selectedValues),
        allowFreeText: @js((bool) $allowFreeText),
        optionFor(value) {
            return this.options.find(o => o.value === value) || null;
        },
        labelFor(value) {
            const option = this.optionFor(value);
            return option ? option.label : value;
        },
        sublabelFor(value) {
            const option = this.optionFor(value);
            return option ? option.sublabel : null;
        },
        get results() {
            const terms = this.query.trim().toLowerCase().split(/\s+/).filter(Boolean);
            if (terms.length === 0) return [];
            return this.options
                .filter(o => ! this.selectedValues.includes(o.value) && terms.every(t => o.search.includes(t)))
                .slice(0, 10);
        },
        get canAddFreeText() {
            if (! this.allowFreeText) return false;
            const value = this.query.trim();
            if (value === '') return false;
            const lowered = value.toLowerCase();
            if (this.selectedValues.some(v => v.toLowerCase() === lowered)) return false;
            if (this.options.some(o => o.value.toLowerCase() === lowered)) return false;
            return true;
        },
        add(value) {
            value = String(value);
            if (! this.selectedValues.includes(value)) this.selectedValues.push(value);
            this.query = '';
        },
        addFromInput() {
            if (this.results.length) { this.add(this.results[0].value); return; }
            if (this.canAddFreeText) this.add(this.query.trim());
        },
        remove(value) {
            this.selectedValues = this.selectedValues.filter(v => v !== value);
        },
    }"
    @click.away="query = ''"
    class="mt-1"
>
    <div x-show="selectedValues.length" class="mb-2 flex flex-wrap gap-2">
        <template x-for="value in selectedValues" :key="value">
            <span class="inline-flex items-center gap-1 rounded-full bg-ocean-100 px-2 py-0.5 text-xs font-medium text-ocean-800">
                <span x-text="labelFor(value)"></span>
                <span class="text-ocean-500" x-show="sublabelFor(value)" x-text="sublabelFor(value)"></span>
                <button type="button" @click="remove(value)" class="ml-0.5 text-ocean-500 hover:text-ocean-800" :aria-label="'{{ __('Remove') }} ' + labelFor(value)">&times;</button>
                <input type="hidden" name="{{ $name }}[]" :value="value">
            </span>
        </template>
    </div>

    <div class="relative">
        <input
            type="text"
            x-model="query"
            @keydown.enter.prevent="addFromInput()"
            @keydown.escape="query = ''"
            placeholder="{{ $placeholder }}"
            class="block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
            autocomplete="off"
        >

        <ul x-show="results.length || canAddFreeText" x-transition style="display: none;" class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
            <template x-for="opt in results" :key="opt.value">
                <li>
                    <button type="button" @click="add(opt.value)" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100">
                        <span class="font-medium text-gray-800" x-text="opt.label"></span>
                        <span class="text-gray-500" x-show="opt.sublabel" x-text="opt.sublabel"></span>
                    </button>
                </li>
            </template>

            <li x-show="canAddFreeText">
                <button type="button" @click="add(query.trim())" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100">
                    <span class="text-gray-500">{{ __('Add') }}</span>
                    <span class="font-medium text-gray-800" x-text="'“' + query.trim() + '”'"></span>
                </button>
            </li>
        </ul>

        <p x-show="query.trim() !== '' && results.length === 0 && ! canAddFreeText" style="display: none;" class="mt-1 text-sm text-gray-500">{{ __('No matches.') }}</p>
    </div>
</div>
