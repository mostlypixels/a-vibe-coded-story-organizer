<x-app-layout>
    <x-page-heading>
        {{ __('New :label', ['label' => $type->label()]) }}
    </x-page-heading>

    <form method="POST" action="{{ route('projects.codex.store', [$project, $type->routeKey()]) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        @include('codex.partials.fields')

        <div class="flex items-center gap-4">
            <x-button variant="primary">{{ __('Create :label', ['label' => $type->label()]) }}</x-button>
            <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-content-muted hover:text-content">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>
