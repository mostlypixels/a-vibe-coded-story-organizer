@props([
    'name',
    'label',
    'events',
    'selected' => null,
    'emptyLabel',
    'windowMin',
    'windowMax',
])

@php
    // The picker name carries the `_id` suffix to match its column (event_id,
    // inception_event_id). The inline-new fields drop it, so the names line up
    // with the controller keys (new_event_title, new_inception_event_title).
    $base = str_ends_with($name, '_id') ? substr($name, 0, -3) : $name;
    $newTitleName = 'new_'.$base.'_title';
    $newDatetimeName = 'new_'.$base.'_datetime';
@endphp

<div x-data="{ newEvent: {{ old($newTitleName) ? 'true' : 'false' }} }">
    <x-input-label :for="$name" :value="$label" />
    <x-select :id="$name" :name="$name" x-bind:disabled="newEvent" class="mt-1 block w-full disabled:bg-surface-sunken disabled:text-content-subtle">
        <option value="">{{ $emptyLabel }}</option>
        @foreach ($events as $event)
            <option value="{{ $event->id }}" @selected(old($name, $selected) == $event->id)>{{ $event->title }} &mdash; {{ $event->event_datetime->format('M j, Y') }}</option>
        @endforeach
    </x-select>
    <x-input-error :messages="$errors->get($name)" class="mt-2" />

    <button type="button" x-show="! newEvent" @click="newEvent = true" class="mt-2 text-sm text-link hover:text-link-hover">
        {{ __('+ New event') }}
    </button>

    <div x-show="newEvent" style="{{ old($newTitleName) ? '' : 'display: none;' }}" class="mt-3 space-y-3 border-l-2 border-border pl-4">
        <div>
            <x-input-label :for="$newTitleName" :value="__('New event title')" />
            <x-text-input :id="$newTitleName" :name="$newTitleName" type="text" class="mt-1 block w-full" :value="old($newTitleName)" />
            <x-input-error :messages="$errors->get($newTitleName)" class="mt-2" />
        </div>
        <div>
            <x-input-label :for="$newDatetimeName" :value="__('New event date & time')" />
            <x-text-input :id="$newDatetimeName" :name="$newDatetimeName" type="datetime-local" class="mt-1 block w-full" :value="old($newDatetimeName)" min="{{ $windowMin }}" max="{{ $windowMax }}" />
            <x-input-error :messages="$errors->get($newDatetimeName)" class="mt-2" />
        </div>

        <p class="text-sm text-content-muted">{{ __('The event is created and joins the Main plotline when you save this page.') }}</p>

        <button type="button" @click="newEvent = false" class="text-sm text-link hover:text-link-hover">
            {{ __('Cancel new event') }}
        </button>
    </div>

    {{ $slot }}
</div>
