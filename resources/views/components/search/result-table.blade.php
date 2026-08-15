@props([
    'domain',
    'results',
    'project',
    'query',
    'mode',
])

{{--
    One domain's search results: an <h3> heading over a full-width x-table (same
    chrome as the entity list pages) with ONE row per matched entity. Columns:
    Name (linked) | Matched in (the fields the terms appeared in) | Preview
    (highlighted snippet) | trailing row-actions (view button).

    A domain with no matches renders NOTHING (no empty-state table) — only
    domains that actually matched appear. The parent view hides a whole section
    when all of its tables are empty (see SearchResults::has*Matches()).

    The column is capped at config('search.cap') rows. A column with more
    matches than the cap gets a "See all N results" link to that domain's own
    paginated page instead of showing every row.

    Props:
      • domain  — the SearchDomain this table renders. Owns the heading text, the
                  edit route, the display-name field, and which rows to pull off
                  $results (see App\Enums\SearchDomain).
      • results — the full SearchResults set the current search produced.
      • project, query, mode — needed to build the "See all" link.
--}}
@php($rows = $domain->rowsFrom($results))
@if ($rows->isNotEmpty())
    <div class="space-y-2">
        <x-heading level="3">{{ __($domain->label()) }}</x-heading>

        <x-table>
            <x-slot:head>
                <x-table-heading>{{ __('Name') }}</x-table-heading>
                <x-table-heading>{{ __('Matched in') }}</x-table-heading>
                <x-table-heading>{{ __('Preview') }}</x-table-heading>
                <x-table-heading />
            </x-slot:head>

            @foreach ($rows->take(config('search.cap')) as $row)
                <x-search.result-row
                    :row="$row"
                    :edit-route="$domain->editRoute()"
                    :name-field="$domain->nameField()"
                    :striped="$loop->even"
                />
            @endforeach

            {{-- More matches than the cap: a right-aligned footer link to this
                 domain's own paginated page, carrying the current query and mode. --}}
            @if ($rows->count() > config('search.cap'))
                <x-slot:foot>
                    <td colspan="4" class="px-4 py-3 text-right text-sm">
                        <a
                            href="{{ route('projects.search.domain', ['project' => $project, 'domain' => $domain->value, 'q' => $query, 'mode' => $mode->value]) }}"
                            class="text-link underline hover:text-link-hover"
                        >
                            {{ __('See all :count results', ['count' => $rows->count()]) }}
                        </a>
                    </td>
                </x-slot:foot>
            @endif
        </x-table>
    </div>
@endif
