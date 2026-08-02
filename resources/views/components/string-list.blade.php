@props([
    'name',
    'values' => [],
    'placeholder' => '',
    'addLabel' => null,
    'removeLabel' => null,
])

@php
    // Small Alpine add/remove-row repeater of free-text inputs submitting {{ $name }}[].
    // Used for aliases; empty rows are dropped server-side by the controller.
    $rows = collect($values)->map(fn ($value) => (string) $value)->values()->all();
    $addLabel = $addLabel ?? __('+ Add row');
    $removeLabel = $removeLabel ?? __('Remove row');
@endphp

<div x-data="{ items: {{ Illuminate\Support\Js::from($rows) }} }" class="mt-1">
    <div class="space-y-2">
        <template x-for="(item, index) in items" :key="index">
            <div class="flex items-center gap-2">
                <x-text-input
                    type="text"
                    name="{{ $name }}[]"
                    x-model="items[index]"
                    placeholder="{{ $placeholder }}"
                    class="block w-full text-sm"
                />
                <button type="button" @click="items.splice(index, 1)" class="text-sm text-danger-surface-content hover:text-danger-surface-content/80" aria-label="{{ $removeLabel }}">&times;</button>
            </div>
        </template>
    </div>

    <button type="button" @click="items.push('')" class="mt-2 text-sm text-link hover:text-link-hover">{{ $addLabel }}</button>
</div>
