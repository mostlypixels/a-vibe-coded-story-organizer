{{--
    Attribute timeline editor (edit page only). Rendered OUTSIDE the main entry form: each
    period is its own small form posting to the upsert/destroy routes, and HTML forbids nested
    forms. All ordering / period math is pre-computed by AttributeTimeline in the controller
    ($sheets); this template only renders it.

    $sheets: collection of ['attribute' => CodexAttribute, 'baseline' => ?CodexAttributeValue, 'periods' => Collection]
    $startEvent: the locked Start anchor.  $events: anchor choices for "Add period".

    The outer border-t + pt-10 mark this as its own section, separate from the entry
    form above (which sits directly on the page, not inside a card).
--}}
@if ($sheets->isNotEmpty())
    <div class="border-t border-gray-200 pt-10">
        <x-card :title="__('Attribute timeline')">
        <p class="text-sm text-gray-500">
            {{ __('Each attribute\'s value over time. A period runs from its event until the next change. Editing a value and pressing Save updates it in place.') }}
        </p>

        {{-- Store failures land under these keys; the destroy guard is now a 403, so the old
             'attribute_value' bag no longer feeds this card. Errors share the default bag
             across the card's small forms (accepted at this scale — Q3). --}}
        <x-input-error :messages="$errors->get('value')" class="mt-2" />
        <x-input-error :messages="$errors->get('start_event_id')" class="mt-2" />

        <div class="mt-6 space-y-8">
            @foreach ($sheets as $sheet)
                @php
                    $attribute = $sheet['attribute'];
                @endphp
                <div>
                    <h3 class="font-semibold text-gray-800">{{ $attribute->name }}</h3>

                    <div class="mt-2 space-y-2">
                        {{-- Start baseline: event locked, value editable via upsert, no remove
                             (parallels how is_fixed events / the main plotline hide delete). --}}
                        <form method="POST" action="{{ route('codex.attribute-values.store', [$entry, $attribute]) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="hidden" name="start_event_id" value="{{ $startEvent->id }}">
                            <span class="inline-flex items-center gap-1 w-40 shrink-0 text-sm font-medium text-gray-700">
                                <span aria-hidden="true">&#9679;</span>
                                {{ $startEvent->title }}
                            </span>
                            <label class="sr-only" for="baseline_{{ $attribute->id }}">{{ __('Value from :event', ['event' => $startEvent->title]) }}</label>
                            <x-text-input
                                id="baseline_{{ $attribute->id }}"
                                name="value"
                                type="text"
                                class="flex-1 min-w-[10rem]"
                                :value="old('value', $sheet['baseline']?->value)"
                                :placeholder="__('Starting value')"
                            />
                            <x-icon-save-button />
                        </form>

                        {{-- Later periods: each editable (upsert) with a separate remove form. --}}
                        @foreach ($sheet['periods'] as $period)
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('codex.attribute-values.store', [$entry, $attribute]) }}" class="flex flex-1 flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="start_event_id" value="{{ $period->start_event_id }}">
                                    <span class="inline-flex items-center gap-1 w-40 shrink-0 text-sm font-medium text-gray-700">
                                        <span aria-hidden="true">&#9679;</span>
                                        {{ $period->startEvent->title }}
                                    </span>
                                    <label class="sr-only" for="period_{{ $period->id }}">{{ __('Value from :event', ['event' => $period->startEvent->title]) }}</label>
                                    <x-text-input
                                        id="period_{{ $period->id }}"
                                        name="value"
                                        type="text"
                                        class="flex-1 min-w-[10rem]"
                                        :value="old('value', $period->value)"
                                    />
                                    <x-icon-save-button />
                                </form>
                                <x-icon-delete-button :action="route('codex.attribute-values.destroy', $period)" :confirm="__('Remove this period?')" />
                            </div>
                        @endforeach

                        {{-- Add a period at another event. Posting an event that already has a
                             value simply updates it (the store route is an upsert). --}}
                        <form method="POST" action="{{ route('codex.attribute-values.store', [$entry, $attribute]) }}" class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-2">
                            @csrf
                            <label class="sr-only" for="add_event_{{ $attribute->id }}">{{ __('Add period at event') }}</label>
                            <select
                                id="add_event_{{ $attribute->id }}"
                                name="start_event_id"
                                class="w-40 shrink-0 border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm text-sm"
                                required
                            >
                                <option value="">{{ __('Add period at…') }}</option>
                                @foreach ($events as $event)
                                    <option value="{{ $event->id }}" @selected(old('start_event_id') == $event->id)>{{ $event->title }} — {{ $event->event_datetime->format('M j, Y') }}</option>
                                @endforeach
                            </select>
                            <label class="sr-only" for="add_value_{{ $attribute->id }}">{{ __('New value') }}</label>
                            <x-text-input
                                id="add_value_{{ $attribute->id }}"
                                name="value"
                                type="text"
                                class="flex-1 min-w-[10rem]"
                                :value="old('value')"
                                :placeholder="__('New value')"
                            />
                            <x-button variant="primary" type="submit">{{ __('Add') }}</x-button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>
    </div>
@endif
