<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }} &mdash; {{ __('Plotlines') }}
            </h2>
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to Project') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <x-text-input type="text" name="search" placeholder="{{ __('Search by name...') }}" class="text-sm" :value="request('search')" />
                    <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
                    @if (request()->filled('search'))
                        <a href="{{ route('projects.plotlines.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                    @endif
                </form>

                <a href="{{ route('projects.plotlines.create', $project) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('New Plotline') }}
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <x-sortable-header field="name" :sort="$sort" :direction="$direction">{{ __('Name') }}</x-sortable-header>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Description') }}</th>
                            <th scope="col" class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plotlines as $plotline)
                            <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-semibold text-gray-800 flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $plotline->color }}"></span>
                                        {{ $plotline->name }}
                                        @if ($plotline->is_main)
                                            <span class="text-xs text-gray-400">({{ __('Main') }})</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $plotline->description }}</td>
                                <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-icon-edit-link :href="route('plotlines.edit', $plotline)" />
                                        @unless ($plotline->is_main)
                                            <x-icon-delete-button :action="route('plotlines.destroy', $plotline)" :confirm="__('Are you sure you want to delete this plotline?')" />
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-500">
                                    {{ __('No plotlines match.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
