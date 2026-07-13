<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('New :label', ['label' => $type->label()]) }}
        </x-heading>
    </x-slot>

    <form method="POST" action="{{ route('projects.codex.store', [$project, $type->routeKey()]) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        @include('codex.partials.fields')

        <div class="flex items-center gap-4">
            <x-button variant="primary">{{ __('Create :label', ['label' => $type->label()]) }}</x-button>
            <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>
