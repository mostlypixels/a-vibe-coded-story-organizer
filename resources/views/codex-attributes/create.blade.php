<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Attribute') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form method="POST" action="{{ route('projects.codex-attributes.store', $project) }}" class="space-y-6">
                    @csrf

                    @include('codex-attributes.partials.fields')

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Create Attribute') }}</x-primary-button>
                        <a href="{{ route('projects.codex-attributes.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
