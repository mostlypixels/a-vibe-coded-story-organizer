@php
    $options = $locations->map(fn ($location) => [
        'value' => $location->id,
        'label' => $location->name,
    ])->values()->all();
@endphp

<div>
    <x-input-label :value="__('Locations')" />
    <p class="text-sm text-content-muted">{{ __('Where the event happens (optional).') }}</p>
    <x-chip-picker
        name="locations"
        :options="$options"
        :selected="$selected"
        :label="__('Locations')"
        :placeholder="__('Search locations…')"
    />
    <x-input-error :messages="$errors->get('locations')" class="mt-2" />
</div>
