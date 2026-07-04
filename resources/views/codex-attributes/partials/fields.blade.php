@php
    // $attribute is null on create, the CodexAttribute model on edit.
    $attribute ??= null;

    // Which types are currently ticked: old input on validation failure, otherwise
    // the attribute's stored applies_to (empty on create). applies_to is an enum
    // collection, so map to the raw string values the checkboxes submit.
    $selectedTypes = old('applies_to', $attribute
        ? $attribute->applies_to->map(fn ($type) => $type->value)->all()
        : []);
@endphp

<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $attribute?->name)" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div>
    <x-input-label :value="__('Applies to')" />
    <p class="mt-1 text-sm text-gray-500">{{ __('Choose which entry types show this attribute on their sheet.') }}</p>
    <div class="mt-2 space-y-2">
        @foreach ($types as $type)
            <label class="flex items-center gap-2">
                <input type="checkbox" name="applies_to[]" value="{{ $type->value }}" @checked(in_array($type->value, $selectedTypes)) class="rounded border-gray-300 text-ocean-600 focus:ring-ocean-500">
                <span>{{ $type->label() }}</span>
            </label>
        @endforeach
    </div>
    <p class="mt-2 text-sm text-gray-500">{{ __('Un-ticking a type hides its existing values from sheets and as-of panels but does not delete them — they return if you re-tick the type.') }}</p>
    <x-input-error :messages="$errors->get('applies_to')" class="mt-2" />
    <x-input-error :messages="$errors->get('applies_to.*')" class="mt-2" />
</div>
