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

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($project->description)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        {{ $project->description }}
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-lg text-gray-800">{{ __('Plotlines') }}</h3>
                <a href="{{ route('projects.plotlines.create', $project) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('New Plotline') }}
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @forelse ($project->plotlines as $plotline)
                        <div class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-gray-200' : '' }}">
                            <div>
                                <div class="font-semibold text-gray-800">{{ $plotline->name }}</div>
                                @if ($plotline->description)
                                    <div class="text-sm text-gray-500">{{ $plotline->description }}</div>
                                @endif
                            </div>
                            <div class="flex items-center gap-4 text-sm">
                                @if ($plotline->is_main)
                                    <span class="text-gray-400">{{ __('Main') }}</span>
                                @endif
                                <a href="{{ route('plotlines.edit', $plotline) }}" class="text-gray-500 hover:text-gray-700">{{ __('Edit') }}</a>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500">{{ __('This project has no plotlines yet.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-lg text-gray-800">{{ __('Events') }}</h3>
                <a href="{{ route('projects.events.create', $project) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('New Event') }}
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @forelse ($project->events->sortBy('event_datetime') as $event)
                        <div class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-gray-200' : '' }}">
                            <div>
                                <div class="font-semibold text-gray-800">{{ $event->title }}</div>
                                <div class="text-sm text-gray-500">{{ $event->event_datetime->format('M j, Y g:i A') }}</div>
                                @if ($event->description)
                                    <div class="text-sm text-gray-500">{{ $event->description }}</div>
                                @endif
                                <div class="text-sm text-gray-400">{{ $event->plotlines->pluck('name')->join(', ') }}</div>
                            </div>
                            <div class="flex items-center gap-4 text-sm">
                                <a href="{{ route('events.edit', $event) }}" class="text-gray-500 hover:text-gray-700">{{ __('Edit') }}</a>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500">{{ __('This project has no events yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
