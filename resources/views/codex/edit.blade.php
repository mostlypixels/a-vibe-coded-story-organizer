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
        <form id="codex-entry-edit-form" method="POST" action="{{ route('codex.update', $entry) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            @include('codex.partials.fields')

            <div class="flex items-center gap-4">
                <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
            </div>
        </form>

        {{-- Timeline editor lives outside the main form: its per-period forms post to the
             upsert/destroy routes independently (nested forms are invalid HTML). --}}
        @include('codex.partials.attribute-timeline')

        {{-- Referenced in scenes: read-only view of the derived scene_codex_entry cache, in
             timeline (event) order. Scenes with no assigned event are labelled distinctly, not
             hidden. Full-width, below the timeline rather than in the sidebar. --}}
        <x-card :title="__('Referenced in scenes')">
            @if ($referencingScenes->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No scenes reference this entry yet.') }}</p>
            @else
                <ul class="space-y-2">
                    @foreach ($referencingScenes as $scene)
                        <li>
                            <a href="{{ route('scenes.edit', $scene) }}" class="text-sm text-ocean-600 hover:text-ocean-800">
                                {{ $scene->chapter->act->name }} &mdash; {{ $scene->chapter->name }} &mdash; {{ $scene->name }}
                            </a>
                            @if ($scene->event)
                                <span class="block text-xs text-gray-400">{{ $scene->event->title }} &mdash; {{ $scene->event->event_datetime->format('M j, Y') }}</span>
                            @else
                                <span class="block text-xs text-gray-400">{{ __('No event assigned') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
