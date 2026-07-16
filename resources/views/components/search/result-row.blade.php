@props([
    'row',
    'editRoute',
    'nameField' => 'name',
    'striped' => false,
])

{{--
    One matched entity. Renders the entity's name as a link to its existing edit
    page, the list of fields the terms matched in ("Name, Contents"), the
    pre-built highlighted text preview, and a trailing view button (entities have
    no separate show page, so "view" opens the same edit page as the name link).

    The preview ({!! !!}) is the ONE deliberate un-escaped output on the whole search
    page — it is HTML that SearchSnippet already escaped and wrapped in
    <mark class="bg-sun-200"> before the view ever saw it (same trusted-HTML pattern
    as Scene::renderedContents). The entity name and field labels stay auto-escaped
    {{ }} so HTML-special characters in a title render literally, never as markup.
--}}
<x-table-row :striped="$striped">
    {{-- min-w keeps the w-full preview cell from squeezing names into a wrap-per-word sliver. --}}
    <td class="px-4 py-3 align-top min-w-48">
        <a href="{{ route($editRoute, $row->entity) }}" class="font-medium text-ocean-600 hover:text-ocean-800 hover:underline">
            {{ $row->entity->{$nameField} }}
        </a>
    </td>
    <td class="px-4 py-3 align-top text-sm text-gray-500 whitespace-nowrap">
        {{ $row->matchedFields() }}
    </td>
    <td class="px-4 py-3 align-top text-sm text-gray-700 w-full">
        {!! $row->snippet !!}
    </td>
    <td class="px-4 py-3 align-top text-right text-sm whitespace-nowrap">
        <x-icon-view-link :href="route($editRoute, $row->entity)" />
    </td>
</x-table-row>
