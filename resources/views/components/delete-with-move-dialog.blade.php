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

{{--
    Reusable "move children elsewhere, or delete everything" confirmation dialog for a
    parent entity that owns positioned children (Act → chapters, Chapter → scenes,
    Book → acts). Built on <x-dialog> because a native confirm() cannot render the
    destination <select>. Open it by dispatching `open-modal` with this dialog's `name`.

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
      - tertiaryCount / tertiarySingular / tertiaryPlural — OPTIONAL great-grandchildren
        (e.g. a book's scenes, below its acts and their chapters). Only meaningful
        alongside a secondary count — a three-level entity like Book.
      - destinationField — the form field the destination id is submitted as
        (defaults to the `move_children_to` field both destroy actions read).

    Chapter delete shares this component unchanged — only the props differ.
--}}
@php
    // "3 chapters" / "1 chapter" via this app's inline trans_choice convention.
    $countPhrase = fn (int $count, string $singular, string $plural) => trans_choice(
        '{1} :count '.$singular.'|[2,*] :count '.$plural,
        $count,
        ['count' => $count],
    );

    $childPhrase = $countPhrase($childCount, $childSingular, $childPlural);

    // The full honest cascade phrase for "delete everything": direct children plus,
    // for a two- or three-level entity, its grandchildren and great-grandchildren.
    // With no further counts it collapses back to just the child phrase; with one or
    // two more, they join the same way ProjectDeleteWarning's category list does.
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
    {{--
        The footer slot is rendered by <x-dialog> OUTSIDE this <form> element, so a
        plain submit button in it belongs to no form and does nothing when clicked.
        The `form` attribute below re-associates it by id — the same fix
        <x-edit-actions> uses for its sidebar Save buttons.
    --}}
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
