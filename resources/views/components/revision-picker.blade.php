@props([
    'side',            // 'from' or 'to' — which half of the pair this picker sets
    'label',           // visible label, e.g. "Older"
    'points',          // Collection<SavePoint>, newest first
    'selected',        // the SavePoint currently chosen on this side
    'pair',            // both sides as ['from' => saveId, 'to' => saveId]
    'disabledBefore' => null, // SavePoint: everything at or older than this is unselectable
])

@php
    use Illuminate\Support\Str;

    // The option list is serialised once, server-side, and both halves of the
    // component read it: the <select> baseline and the combobox that enhances
    // it. One source, so the two can never label the same save differently.
    $cutoff = $disabledBefore === null
        ? null
        : $points->search(fn ($point) => $point->saveId === $disabledBefore->saveId);

    $options = $points->values()->map(function ($point, $index) use ($points, $cutoff) {
        $parts = ['#'.($points->count() - $index), $point->savedAt->format('d M H:i')];

        if ($point->label) {
            $parts[] = $point->label;
        }

        $parts[] = Str::headline($point->origin->value);

        if ($point->isCurrent) {
            $parts[] = __('Current');
        }

        return [
            'id' => $point->saveId,
            'label' => implode(' · ', $parts),
            'origin' => $point->origin->value,
            'savedAt' => $point->savedAt->format('Y-m-d'),
            'isCurrent' => $point->isCurrent,
            // Decided here, not in the browser: the invalid pairing has to be
            // unreachable with JS off too.
            'disabled' => $cutoff !== false && $cutoff !== null && $index >= $cutoff,
        ];
    })->all();

    $listboxId = "{$side}-listbox";
    $labelId = "{$side}-label";
@endphp

{{--
    One side of the compare pair.

    Progressive enhancement, in the shape x-wysiwyg already uses: the native
    <select> is what the server renders and what a browser with no JS keeps
    using, and the combobox below it appears only once Alpine has mounted
    (`x-show="ready"` + an inline display:none, so there is no flash of both).

    Why a combobox rather than the <select> everywhere: the panel carries
    filters — manual-only and a date range — and a <select> cannot hold them.
    They are per side and deliberately not synced, because finding the save that
    broke something means comparing one manual checkpoint against the autosaves
    around it.

    Built to the W3C APG select-only combobox pattern; the roles and states are
    the contract, not decoration. See resources/js/revision-picker.js.
--}}
<div
    x-data="revisionPicker({
        side: @js($side),
        options: @js($options),
        selectedId: @js($selected?->saveId),
        labelledBy: @js($labelId),
        pair: @js($pair),
    })"
    class="relative"
>
    <span id="{{ $labelId }}" class="block font-medium text-sm text-content-muted">{{ $label }}</span>

    {{-- The baseline. Alpine hides it on mount; without Alpine it is the whole
         control, and the form's Compare button submits it. --}}
    <div x-show="! ready">
        <x-select name="{{ $side }}" aria-labelledby="{{ $labelId }}" class="mt-1 block w-full text-sm">
            @foreach ($options as $option)
                <option
                    value="{{ $option['id'] }}"
                    @selected($option['id'] === $selected?->saveId)
                    @disabled($option['disabled'])
                >{{ $option['label'] }}</option>
            @endforeach
        </x-select>
    </div>

    <div x-show="ready" style="display: none;">
        <button
            type="button"
            x-ref="trigger"
            role="combobox"
            aria-haspopup="listbox"
            :aria-expanded="open"
            aria-controls="{{ $listboxId }}"
            aria-labelledby="{{ $labelId }}"
            :aria-activedescendant="open ? activeId : null"
            x-on:click="togglePanel()"
            x-on:keydown.down.prevent="move(1)"
            x-on:keydown.up.prevent="move(-1)"
            x-on:keydown.enter.prevent="open ? chooseActive() : openPanel()"
            x-on:keydown.escape.prevent="closePanel()"
            x-on:keydown.home.prevent="open && jump('first')"
            x-on:keydown.end.prevent="open && jump('last')"
            class="mt-1 flex w-full items-center justify-between gap-2 rounded-md border border-border-strong bg-surface-raised px-3 py-2 text-left text-sm shadow-xs focus:border-focus focus:outline-hidden focus:ring-1 focus:ring-focus"
        >
            <span class="truncate" x-text="selected ? selected.label : @js(__('Choose a save'))"></span>
            <x-tabler-chevron-down class="h-4 w-4 shrink-0 text-content-subtle" aria-hidden="true" />
        </button>

        <div
            x-show="open"
            x-on:keydown.escape.prevent="closePanel()"
            x-on:click.outside="closePanel({ refocus: false })"
            style="display: none;"
            class="absolute z-20 mt-1 w-full rounded-md border border-border bg-surface-overlay shadow-lg"
        >
            {{-- The filters that a <select> could not have held. --}}
            <div class="space-y-2 border-b border-border p-3">
                <label class="block">
                    <span class="sr-only">{{ __('Filter saves') }}</span>
                    <x-text-input
                        type="search"
                        x-ref="search"
                        x-model="query"
                        x-on:input="onFilterChange()"
                        x-on:keydown.down.prevent="move(1)"
                        x-on:keydown.up.prevent="move(-1)"
                        x-on:keydown.enter.prevent="chooseActive()"
                        x-on:keydown.escape.prevent="closePanel()"
                        x-on:keydown.home.prevent="jump('first')"
                        x-on:keydown.end.prevent="jump('last')"
                        placeholder="{{ __('Filter saves…') }}"
                        class="block w-full text-sm"
                    />
                </label>

                <label class="flex items-center gap-2 text-sm text-content-muted">
                    <input
                        type="checkbox"
                        x-model="manualOnly"
                        x-on:change="onFilterChange()"
                        class="rounded-sm border-border-strong text-link focus:ring-focus"
                    >
                    {{ __('Manual saves only') }}
                </label>

                <div class="flex items-center gap-2 text-sm">
                    <label class="flex-1">
                        <span class="sr-only">{{ __('From date') }}</span>
                        <x-text-input type="date" x-model="dateFrom" x-on:change="onFilterChange()" class="block w-full text-sm" />
                    </label>
                    <span class="text-content-subtle" aria-hidden="true">&rarr;</span>
                    <label class="flex-1">
                        <span class="sr-only">{{ __('To date') }}</span>
                        <x-text-input type="date" x-model="dateTo" x-on:change="onFilterChange()" class="block w-full text-sm" />
                    </label>
                </div>
            </div>

            <ul
                id="{{ $listboxId }}"
                role="listbox"
                aria-labelledby="{{ $labelId }}"
                class="max-h-64 overflow-y-auto py-1"
            >
                <template x-for="(option, index) in visible" :key="option.id">
                    <li
                        :id="optionId(option)"
                        role="option"
                        :aria-selected="option.id === selectedId"
                        :aria-disabled="option.disabled"
                        x-on:click="choose(option)"
                        x-on:mousemove="! option.disabled && (activeIndex = index)"
                        :class="{
                            'bg-accent-surface text-accent-content': index === activeIndex && ! option.disabled,
                            'text-content-subtle cursor-not-allowed': option.disabled,
                            'text-content-muted cursor-pointer': ! option.disabled && index !== activeIndex,
                        }"
                        class="px-3 py-1.5 text-sm"
                        x-text="option.label"
                    ></li>
                </template>

                <li x-show="visible.length === 0" class="px-3 py-2 text-sm text-content-subtle">
                    {{ __('No saves match these filters.') }}
                </li>
            </ul>
        </div>
    </div>
</div>
