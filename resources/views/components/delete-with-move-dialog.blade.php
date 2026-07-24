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
    'destinationField' => 'move_children_to',
])

{{--
    Reusable "move children elsewhere, or delete everything" confirmation dialog for a
    parent entity that owns positioned children (Act → chapters, Chapter → scenes).
    Built on <x-dialog> because a native confirm() cannot render the destination
    <select>. Open it by dispatching `open-modal` with this dialog's `name`.

    Props:
      - childCount / childSingular / childPlural — the DIRECT children that get moved
        (e.g. 3 / "chapter" / "chapters"). Drives the "Move …" option's wording.
      - destinationNoun — the singular destination type ("act"), for "another act".
      - destinations — the sibling entities the children can move to. When empty, the
        radio choice collapses to a single informational line (delete-only), same end
        state as the old plain confirm().
      - secondaryCount / secondarySingular / secondaryPlural — OPTIONAL grandchildren
        (e.g. an act's scenes) folded into the honest "delete everything" summary only.
        Omit them for a one-level entity like Chapter.
      - destinationField — the form field the destination id is submitted as
        (defaults to the `move_children_to` field both destroy actions read).

    This component is shared by task 05 (Chapter delete) unchanged — only the props differ.
--}}
@php
    // "3 chapters" / "1 chapter" via this app's inline trans_choice convention.
    $childPhrase = trans_choice(
        '{1} :count '.$childSingular.'|[2,*] :count '.$childPlural,
        $childCount,
        ['count' => $childCount],
    );

    // The full honest cascade phrase for "delete everything": direct children plus,
    // for a two-level entity, its grandchildren. With no secondary count it collapses
    // back to just the child phrase.
    $cascadePhrase = $childPhrase;

    if ($secondarySingular !== null && $secondaryCount > 0) {
        $secondaryPhrase = trans_choice(
            '{1} :count '.$secondarySingular.'|[2,*] :count '.$secondaryPlural,
            $secondaryCount,
            ['count' => $secondaryCount],
        );

        $cascadePhrase = __(':children and :grandchildren', [
            'children' => $childPhrase,
            'grandchildren' => $secondaryPhrase,
        ]);
    }
@endphp

<x-dialog :name="$name" :title="$title">
    <form method="POST" action="{{ $action }}" x-data="{ mode: 'move' }" class="space-y-4">
        @csrf
        @method('DELETE')

        @if ($destinations->isNotEmpty())
            <label class="flex items-start gap-2">
                <input type="radio" name="delete_mode" value="move" x-model="mode" class="mt-1">
                <span class="text-sm text-gray-700">{{ __('Move :children to another :destination, then delete', ['children' => $childPhrase, 'destination' => $destinationNoun]) }}</span>
            </label>

            <div x-show="mode === 'move'" class="pl-6">
                <x-input-label for="{{ $name }}-destination" :value="__('Destination')" class="sr-only" />
                <select
                    id="{{ $name }}-destination"
                    name="{{ $destinationField }}"
                    x-bind:required="mode === 'move'"
                    x-bind:disabled="mode !== 'move'"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-ocean-500 focus:ring-ocean-500 sm:text-sm"
                >
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-start gap-2">
                <input type="radio" name="delete_mode" value="delete" x-model="mode" class="mt-1">
                <span class="text-sm text-gray-700">{{ __('Delete everything (:cascade)', ['cascade' => $cascadePhrase]) }}</span>
            </label>
        @else
            <p class="text-sm text-gray-700">{{ __('This will also delete :cascade.', ['cascade' => $cascadePhrase]) }}</p>
        @endif

        <x-slot name="footer">
            <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
            <x-button variant="danger" type="submit">{{ __('Confirm') }}</x-button>
        </x-slot>
    </form>
</x-dialog>
