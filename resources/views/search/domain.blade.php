<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __($domain->label()) }}
    </x-page-heading>

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <p class="text-content-muted">
                {{ __(':label matching “:query”.', ['label' => $domain->label(), 'query' => $query]) }}
            </p>
            <a
                href="{{ route('projects.search.index', ['project' => $project, 'q' => $query, 'mode' => $mode->value]) }}"
                class="text-sm text-content-muted hover:text-content shrink-0"
            >
                {{ __('← Back to search') }}
            </a>
        </div>

        @if ($paginator->isEmpty())
            <div class="bg-surface-raised shadow-xs rounded-lg px-6 py-10 text-center text-content-muted">
                <p class="font-medium text-content-muted">{{ __('No more results.') }}</p>
            </div>
        @else
            <x-table>
                <x-slot:head>
                    <x-table-heading>{{ __('Name') }}</x-table-heading>
                    <x-table-heading>{{ __('Matched in') }}</x-table-heading>
                    <x-table-heading>{{ __('Preview') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @foreach ($paginator as $row)
                    <x-search.result-row
                        :row="$row"
                        :view-route="$domain->viewRoute()"
                        :name-field="$domain->nameField()"
                        :striped="$loop->even"
                    />
                @endforeach
            </x-table>

            {{ $paginator->links() }}
        @endif
    </div>
</x-app-layout>
