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
    page — it is HTML that SearchSnippet already escaped and wrapped in a themed
    <mark> before the view ever saw it (same trusted-HTML pattern as
    Scene::renderedContents). The mark's classes live on SearchSnippet, not here.
    The entity name and field labels stay auto-escaped {{ }} so HTML-special
    characters in a title render literally, never as markup.

    $row->book is set for Act/Chapter/Scene rows only (SearchDomain::carriesBook()) —
    search stays project-wide, so naming the book is what keeps a hit locatable in
    a multi-book project.
--}}
<x-table-row :striped="$striped">
    {{-- min-w keeps the w-full preview cell from squeezing names into a wrap-per-word sliver. --}}
    <td class="px-4 py-3 align-top min-w-48">
        <a href="{{ route($editRoute, $row->entity) }}" class="font-medium text-link hover:text-link-hover hover:underline">
            {{ $row->entity->{$nameField} }}
        </a>
        @if ($row->book)
            <div class="text-xs text-content-muted">{{ $row->book->displayName() }}</div>
        @endif
    </td>
    <td class="px-4 py-3 align-top text-sm text-content-muted whitespace-nowrap">
        {{ $row->matchedFields() }}
    </td>
    <td class="px-4 py-3 align-top text-sm text-content-muted w-full">
        {!! $row->snippet !!}
    </td>
    <td class="px-4 py-3 align-top text-right text-sm whitespace-nowrap">
        <x-icon-view-link :href="route($editRoute, $row->entity)" />
    </td>
</x-table-row>
