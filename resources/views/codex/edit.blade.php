<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit :label', ['label' => $type->label()]) }} &mdash; {{ $entry->name }}
            </h2>
            <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to :label', ['label' => $type->pluralLabel()]) }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="POST" action="{{ route('codex.update', $entry) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                @include('codex.partials.fields')

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
                </div>
            </form>

            {{-- Timeline editor lives outside the main form: its per-period forms post to the
                 upsert/destroy routes independently (nested forms are invalid HTML). --}}
            @include('codex.partials.attribute-timeline')

            <form method="POST" action="{{ route('codex.destroy', $entry) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this entry?') }}')">
                @csrf
                @method('DELETE')
                <x-danger-button>{{ __('Delete :label', ['label' => $type->label()]) }}</x-danger-button>
            </form>
        </div>
    </div>
</x-app-layout>
