{{--
    The input goes in the slot, not in a prop: fields here are text inputs,
    selects, date pickers, colour pickers and file inputs, and a prop-driven
    input would need a branch for each. `name` wires the label, the input id
    and the error key together, which is the pairing that breaks silently
    when a field is written by hand.
--}}

@props([
    'name',
    'label',
    'hint' => null,
])

<div {{ $attributes }}>
    <x-input-label for="{{ $name }}" :value="$label" />
    {{ $slot }}
    @if ($hint)
        <p class="mt-1 text-sm text-content-muted">{{ $hint }}</p>
    @endif
    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
