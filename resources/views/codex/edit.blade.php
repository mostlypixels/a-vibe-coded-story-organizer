<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <x-heading level="2">
                {{ __('Edit :label', ['label' => $type->label()]) }} &mdash; {{ $entry->name }}
            </x-heading>
            <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to :label', ['label' => $type->pluralLabel()]) }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-10">
        <form method="POST" action="{{ route('codex.update', $entry) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            @include('codex.partials.fields')

            <div class="flex items-center gap-4">
                <x-button variant="primary" :icon="true">{{ __('Save') }}</x-button>
                <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
            </div>
        </form>

        {{-- Timeline editor lives outside the main form: its per-period forms post to the
             upsert/destroy routes independently (nested forms are invalid HTML). --}}
        @include('codex.partials.attribute-timeline')

        <x-delete-button :action="route('codex.destroy', $entry)" :confirm="__('Are you sure you want to delete this entry?')">
            {{ __('Delete :label', ['label' => $type->label()]) }}
        </x-delete-button>
    </div>
</x-app-layout>
