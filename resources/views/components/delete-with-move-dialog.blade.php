@props([
    'name',
    'action',
    'title',
    'childCount',
    'childSingular',
    'childPlural',
    'destinationNoun',
    'destinations',
    'secondaryCount' => 0,
    'secondarySingular' => null,
    'secondaryPlural' => null,
    'tertiaryCount' => 0,
    'tertiarySingular' => null,
    'tertiaryPlural' => null,
    'destinationField' => 'move_children_to',
])

@php
    $countPhrase = fn (int $count, string $singular, string $plural) => trans_choice(
        '{1} :count '.$singular.'|[2,*] :count '.$plural,
        $count,
        ['count' => $count],
    );

    $childPhrase = $countPhrase($childCount, $childSingular, $childPlural);

    $cascadeParts = [$childPhrase];

    if ($secondarySingular !== null && $secondaryCount > 0) {
        $cascadeParts[] = $countPhrase($secondaryCount, $secondarySingular, $secondaryPlural);
    }

    if ($tertiarySingular !== null && $tertiaryCount > 0) {
        $cascadeParts[] = $countPhrase($tertiaryCount, $tertiarySingular, $tertiaryPlural);
    }

    $cascadePhrase = count($cascadeParts) > 1
        ? \Illuminate\Support\Arr::join($cascadeParts, ', ', ' '.__('and').' ')
        : $cascadeParts[0];
@endphp

<x-dialog :name="$name" :title="$title">
    <form id="{{ $name }}-form" method="POST" action="{{ $action }}" x-data="{ mode: 'move' }" class="space-y-4">
        @csrf
        @method('DELETE')

        @if ($destinations->isNotEmpty())
            <label class="flex items-start gap-2">
                <input type="radio" name="delete_mode" value="move" x-model="mode" class="mt-1">
                <span class="text-sm text-content-muted">{{ __('Move :children to another :destination, then delete', ['children' => $childPhrase, 'destination' => $destinationNoun]) }}</span>
            </label>

            <div x-show="mode === 'move'" class="pl-6">
                <x-input-label for="{{ $name }}-destination" :value="__('Destination')" class="sr-only" />
                <x-select
                    id="{{ $name }}-destination"
                    name="{{ $destinationField }}"
                    x-bind:required="mode === 'move'"
                    x-bind:disabled="mode !== 'move'"
                    class="mt-1 block w-full sm:text-sm"
                >
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                    @endforeach
                </x-select>
            </div>

            <label class="flex items-start gap-2">
                <input type="radio" name="delete_mode" value="delete" x-model="mode" class="mt-1">
                <span class="text-sm text-content-muted">{{ __('Delete everything (:cascade)', ['cascade' => $cascadePhrase]) }}</span>
            </label>
        @else
            <p class="text-sm text-content-muted">{{ __('This will also delete :cascade.', ['cascade' => $cascadePhrase]) }}</p>
        @endif

        <x-slot name="footer">
            <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
            <x-button variant="danger" type="submit" form="{{ $name }}-form">{{ __('Confirm') }}</x-button>
        </x-slot>
    </form>
</x-dialog>
