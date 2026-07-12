<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
            <div class="flex justify-end">
                <x-button variant="primary" :href="route('projects.create')">{{ __('New Project') }}</x-button>
            </div>

            <x-card class="text-gray-900">
                @forelse ($projects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="block py-3 {{ !$loop->last ? 'border-b border-gray-200' : '' }}">
                        <div class="font-semibold text-gray-800">{{ $project->name }}</div>
                        @if ($project->description)
                            <div class="text-sm text-gray-500"><x-rich-text-excerpt :html="$project->description" /></div>
                        @endif
                    </a>
                @empty
                    <p class="text-gray-500">{{ __('You have no projects yet.') }}</p>
                @endforelse
            </x-card>
    </div>
</x-app-layout>
