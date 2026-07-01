<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex justify-end">
                <a href="{{ route('projects.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('New Project') }}
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @forelse ($projects as $project)
                        <a href="{{ route('projects.show', $project) }}" class="block py-3 {{ !$loop->last ? 'border-b border-gray-200' : '' }}">
                            <div class="font-semibold text-gray-800">{{ $project->name }}</div>
                            @if ($project->description)
                                <div class="text-sm text-gray-500">{{ $project->description }}</div>
                            @endif
                        </a>
                    @empty
                        <p class="text-gray-500">{{ __('You have no projects yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
