@php
    $attribute ??= null;

    $selectedTypes = old('applies_to', $attribute
        ? $attribute->applies_to->map(fn ($type) => $type->value)->all()
        : []);
@endphp

<x-field name="name" :label="__('Name')">
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $attribute?->name)" required autofocus />
</x-field>

<div>
    <x-input-label :value="__('Applies to')" />
    <p class="mt-1 text-sm text-content-muted">{{ __('Choose which entry types show this attribute on their sheet.') }}</p>
    <div class="mt-2 space-y-2">
        @foreach ($types as $type)
            <label class="flex items-center gap-2">
                <input type="checkbox" name="applies_to[]" value="{{ $type->value }}" @checked(in_array($type->value, $selectedTypes)) class="rounded-sm border-border-strong text-link focus:ring-focus">
                <span>{{ $type->label() }}</span>
            </label>
        @endforeach
    </div>
    <p class="mt-2 text-sm text-content-muted">{{ __('Un-ticking a type hides its existing values from sheets and as-of panels but does not delete them — they return if you re-tick the type.') }}</p>
    <x-input-error :messages="$errors->get('applies_to')" class="mt-2" />
    <x-input-error :messages="$errors->get('applies_to.*')" class="mt-2" />
</div>
