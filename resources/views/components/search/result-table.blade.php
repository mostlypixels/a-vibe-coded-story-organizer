@props([
    'domain',
    'results',
    'project',
    'query',
    'mode',
])

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

            @if ($rows->count() > config('search.cap'))
                <x-slot:foot>
                    <x-table-cell colspan="4" align="right" sm>
                        <a
                            href="{{ route('projects.search.domain', ['project' => $project, 'domain' => $domain->value, 'q' => $query, 'mode' => $mode->value]) }}"
                            class="text-link underline hover:text-link-hover"
                        >
                            {{ __('See all :count results', ['count' => $rows->count()]) }}
                        </a>
                    </x-table-cell>
                </x-slot:foot>
            @endif
        </x-table>
    </div>
@endif
