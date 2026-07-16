@props([
    'title',
    'rows',
    'editRoute',
    'nameField' => 'name',
])

{{--
    One entity type's search results: an <h3> heading over a full-width x-table
    (same chrome as the entity list pages) with ONE row per matched entity.
    Columns: Name (linked) | Matched in (the fields the terms appeared in) |
    Preview (highlighted snippet) | trailing row-actions (view button).

    An entity type with no matches renders NOTHING (no empty-state table) — only
    types that actually matched appear. The parent view hides a whole section
    when all of its tables are empty (see SearchResults::has*Matches()).

    Props:
      • title     — the table heading (e.g. "Scenes", "Characters").
      • rows      — Collection<App\Support\SearchResultRow> for this entity type.
      • editRoute — the named route each row links to (e.g. "scenes.edit"); the row
                    passes its entity to route() to build the edit URL.
      • nameField — the entity attribute holding the display name ("name" for most,
                    "title" for Event).
--}}
@if ($rows->isNotEmpty())
    <div class="space-y-2">
        <x-heading level="3">{{ $title }}</x-heading>

        <x-table>
            <x-slot:head>
                <x-table-heading>{{ __('Name') }}</x-table-heading>
                <x-table-heading>{{ __('Matched in') }}</x-table-heading>
                <x-table-heading>{{ __('Preview') }}</x-table-heading>
                <x-table-heading />
            </x-slot:head>

            @foreach ($rows as $row)
                <x-search.result-row
                    :row="$row"
                    :edit-route="$editRoute"
                    :name-field="$nameField"
                    :striped="$loop->even"
                />
            @endforeach
        </x-table>
    </div>
@endif
