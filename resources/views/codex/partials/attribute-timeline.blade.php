<div class="border-t border-border pt-10">
    <x-card :title="__('Attribute timeline')">
        <p class="text-sm text-content-muted">
            {{ __('Each attribute\'s value over time. A period runs from its event until the next change. Editing a value and pressing Save updates it in place.') }}
        </p>

        <x-input-error :messages="$errors->get('value')" class="mt-2" />
        <x-input-error :messages="$errors->get('start_event_id')" class="mt-2" />
        <x-input-error :messages="$errors->get('codex_attribute_id')" class="mt-2" />

        @if ($sheets->isEmpty())
            <p class="mt-6 text-sm text-content-subtle">{{ __('No attributes are attached to this entry yet.') }}</p>
        @endif

        <div class="mt-6 space-y-8">
            @foreach ($sheets as $sheet)
                @php
                    $attribute = $sheet['attribute'];
                @endphp
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-content">{{ $attribute->name }}</h3>
                        <x-icon-delete-button
                            :action="route('codex.attributes.detach', [$entry, $attribute])"
                            :confirm="__('Remove :attribute from :entry? Its values are deleted. The attribute stays in the project.', ['attribute' => $attribute->name, 'entry' => $entry->name])"
                            :label="__('Remove :attribute', ['attribute' => $attribute->name])"
                        />
                    </div>

                    <div class="mt-2 space-y-2">
                        <form method="POST" action="{{ route('codex.attribute-values.store', [$entry, $attribute]) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="hidden" name="start_event_id" value="{{ $startEvent->id }}">
                            <span class="inline-flex items-center gap-1 w-40 shrink-0 text-sm font-medium text-content-muted">
                                <span aria-hidden="true">&#9679;</span>
                                {{ $startEvent->title }}
                            </span>
                            <label class="sr-only" for="baseline_{{ $attribute->id }}">{{ __('Value from :event', ['event' => $startEvent->title]) }}</label>
                            <x-text-input
                                id="baseline_{{ $attribute->id }}"
                                name="value"
                                type="text"
                                class="flex-1 min-w-40"
                                :value="old('value', $sheet['baseline']?->value)"
                                :placeholder="__('Starting value')"
                            />
                            <x-icon-save-button />
                        </form>

                        @foreach ($sheet['periods'] as $period)
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('codex.attribute-values.store', [$entry, $attribute]) }}" class="flex flex-1 flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="start_event_id" value="{{ $period->start_event_id }}">
                                    <span class="inline-flex items-center gap-1 w-40 shrink-0 text-sm font-medium text-content-muted">
                                        <span aria-hidden="true">&#9679;</span>
                                        {{ $period->startEvent->title }}
                                    </span>
                                    <label class="sr-only" for="period_{{ $period->id }}">{{ __('Value from :event', ['event' => $period->startEvent->title]) }}</label>
                                    <x-text-input
                                        id="period_{{ $period->id }}"
                                        name="value"
                                        type="text"
                                        class="flex-1 min-w-40"
                                        :value="old('value', $period->value)"
                                    />
                                    <x-icon-save-button />
                                </form>
                                <x-icon-delete-button :action="route('codex.attribute-values.destroy', $period)" :confirm="__('Remove this period?')" />
                            </div>
                        @endforeach

                        <form method="POST" action="{{ route('codex.attribute-values.store', [$entry, $attribute]) }}" class="flex flex-wrap items-center gap-2 border-t border-border pt-2">
                            @csrf
                            <label class="sr-only" for="add_event_{{ $attribute->id }}">{{ __('Add period at event') }}</label>
                            <x-select
                                id="add_event_{{ $attribute->id }}"
                                name="start_event_id"
                                class="w-40 shrink-0 text-sm"
                                required
                            >
                                <option value="">{{ __('Add period at…') }}</option>
                                @foreach ($events as $event)
                                    <option value="{{ $event->id }}" @selected(old('start_event_id') == $event->id)>{{ $event->title }} — {{ \App\Support\DateFormat::date($event->event_datetime, $locale) }}</option>
                                @endforeach
                            </x-select>
                            <label class="sr-only" for="add_value_{{ $attribute->id }}">{{ __('New value') }}</label>
                            <x-text-input
                                id="add_value_{{ $attribute->id }}"
                                name="value"
                                type="text"
                                class="flex-1 min-w-40"
                                :value="old('value')"
                                :placeholder="__('New value')"
                            />
                            <x-button variant="primary" type="submit">{{ __('Add') }}</x-button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($unattachedAttributes->isNotEmpty())
            <form method="POST" action="{{ route('codex.attributes.attach', $entry) }}" class="mt-8 flex flex-wrap items-center gap-2 border-t border-border pt-6">
                @csrf
                <x-input-label for="attach-codex-attribute" :value="__('Add an attribute')" class="sr-only" />
                <x-select id="attach-codex-attribute" name="codex_attribute_id" class="text-sm" required>
                    <option value="">{{ __('Add an attribute…') }}</option>
                    @foreach ($unattachedAttributes as $attribute)
                        <option value="{{ $attribute->id }}">{{ $attribute->name }}</option>
                    @endforeach
                </x-select>
                <x-button variant="primary" type="submit">{{ __('Add') }}</x-button>
            </form>
        @endif

        <p class="mt-3 text-sm text-content-muted">
            {{ __('These come from your project\'s attributes.') }}
            <a href="{{ route('projects.codex-attributes.index', $project) }}" class="text-link hover:text-link-hover">{{ __('Add or remove attributes') }}</a>
        </p>
    </x-card>
</div>
