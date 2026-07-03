@props(['name', 'events', 'selected' => []])

@php
    // Searchable multi-select for events. All project events are embedded as JSON and
    // filtered client-side by Alpine (fine for hundreds; a server search would be the
    // next step for thousands). Selected events submit as hidden {{ $name }}[] inputs,
    // matching the plain array the controller/validation already expect.
    $options = collect($events)->map(fn ($event) => [
        'id' => (int) $event->id,
        'title' => $event->title,
        'date' => $event->event_datetime->format('M j, Y'),
        'search' => strtolower($event->title.' '.$event->event_datetime->format('M j, Y').' '.$event->event_datetime->format('Y-m-d')),
    ])->values()->all();

    $selectedIds = collect($selected)->map(fn ($id) => (int) $id)->values()->all();
@endphp

<div
    x-data="{
        query: '',
        options: @js($options),
        selectedIds: @js($selectedIds),
        get selectedOptions() {
            return this.options.filter(o => this.selectedIds.includes(o.id));
        },
        get results() {
            const terms = this.query.trim().toLowerCase().split(/\s+/).filter(Boolean);
            if (terms.length === 0) return [];
            return this.options
                .filter(o => ! this.selectedIds.includes(o.id) && terms.every(t => o.search.includes(t)))
                .slice(0, 10);
        },
        add(id) {
            if (! this.selectedIds.includes(id)) this.selectedIds.push(id);
            this.query = '';
        },
        remove(id) {
            this.selectedIds = this.selectedIds.filter(i => i !== id);
        },
    }"
    @click.away="query = ''"
    class="mt-1"
>
    <div x-show="selectedOptions.length" class="mb-2 flex flex-wrap gap-2">
        <template x-for="opt in selectedOptions" :key="opt.id">
            <span class="inline-flex items-center gap-1 rounded-full bg-ocean-100 px-2 py-0.5 text-xs font-medium text-ocean-800">
                <span x-text="opt.title"></span>
                <span class="text-ocean-500" x-text="opt.date"></span>
                <button type="button" @click="remove(opt.id)" class="ml-0.5 text-ocean-500 hover:text-ocean-800" :aria-label="'{{ __('Remove') }} ' + opt.title">&times;</button>
                <input type="hidden" name="{{ $name }}[]" :value="opt.id">
            </span>
        </template>
    </div>

    <div class="relative">
        <input
            type="text"
            x-model="query"
            @keydown.enter.prevent="if (results.length) add(results[0].id)"
            @keydown.escape="query = ''"
            placeholder="{{ __('Search events by name or date…') }}"
            class="block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
            autocomplete="off"
        >

        <ul x-show="results.length" x-transition style="display: none;" class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
            <template x-for="opt in results" :key="opt.id">
                <li>
                    <button type="button" @click="add(opt.id)" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100">
                        <span class="font-medium text-gray-800" x-text="opt.title"></span>
                        <span class="text-gray-500" x-text="opt.date"></span>
                    </button>
                </li>
            </template>
        </ul>

        <p x-show="query.trim() !== '' && results.length === 0" style="display: none;" class="mt-1 text-sm text-gray-500">{{ __('No events match.') }}</p>
    </div>
</div>
