<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }}
            </h2>
            <a href="{{ route('projects.edit', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Edit Project') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
            @if ($project->description)
                <x-card class="text-gray-900">
                    <x-rich-text :html="$project->description" />
                </x-card>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <a href="{{ route('projects.plotlines.index', $project) }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50">
                    <h3 class="font-semibold text-lg text-gray-800">{{ __('Plotlines') }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ trans_choice('{0} No plotlines|{1} :count plotline|[2,*] :count plotlines', $project->plotlines_count, ['count' => $project->plotlines_count]) }}</p>
                </a>

                <a href="{{ route('projects.events.index', $project) }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50">
                    <h3 class="font-semibold text-lg text-gray-800">{{ __('Events') }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ trans_choice('{0} No events|{1} :count event|[2,*] :count events', $project->events_count, ['count' => $project->events_count]) }}</p>
                </a>
            </div>
    </div>
</x-app-layout>
