<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New :label', ['label' => $type->label()]) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('projects.codex.store', [$project, $type->routeKey()]) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                @include('codex.partials.fields')

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Create :label', ['label' => $type->label()]) }}</x-primary-button>
                    <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
