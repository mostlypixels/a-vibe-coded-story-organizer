<x-app-layout>
    <x-page-heading>
        {{ __('Edit :label', ['label' => $type->label()]) }} &mdash; {{ $entry->name }}
    </x-page-heading>

    <div class="space-y-10">
        <form id="codex-entry-edit-form" method="POST" action="{{ route('codex.update', $entry) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            @include('codex.partials.fields')

            <div class="flex items-center gap-4">
                <a href="{{ route('projects.codex.index', [$project, $type->routeKey()]) }}" class="text-sm text-content-muted hover:text-content">{{ __('Cancel') }}</a>
            </div>
        </form>

        {{-- Delete and Duplicate each need their own <form>, so they stay outside the edit
             form — its fields partial fills both columns, and a nested form is invalid HTML.
             The buttons that submit these live in the sidebar and point here by id. --}}
        <form
            id="codex-entry-delete-form"
            method="POST"
            action="{{ route('codex.destroy', $entry) }}"
            onsubmit="return confirm('{{ __('Are you sure you want to delete this entry?') }}')"
        >
            @csrf
            @method('DELETE')
        </form>

        <x-duplicate-dialog
            name="duplicate-codex-entry-{{ $entry->id }}"
            :action="route('codex.duplicate', $entry)"
            :title="__('Duplicate :label', ['label' => $type->label()])"
            :suggestion="$duplicateSuggestion"
        />

        {{-- Timeline editor lives outside the main form: its per-period forms post to the
             upsert/destroy routes independently (nested forms are invalid HTML). --}}
        @include('codex.partials.attribute-timeline')

        {{-- Referenced in scenes: read-only view of the derived scene_codex_entry cache, in
             timeline (event) order. Scenes with no assigned event are labelled distinctly, not
             hidden. Full-width, below the timeline rather than in the sidebar. --}}
        <x-card :title="__('Referenced in scenes')">
            @if ($referencingScenes->isEmpty())
                <p class="text-sm text-content-muted">{{ __('No scenes reference this entry yet.') }}</p>
            @else
                <ul class="space-y-2">
                    @foreach ($referencingScenes as $scene)
                        <li>
                            <a href="{{ route('scenes.edit', $scene) }}" class="text-sm text-link hover:text-link-hover">
                                {{ $scene->chapter->act->name }} &mdash; {{ $scene->chapter->name }} &mdash; {{ $scene->name }}
                            </a>
                            @if ($scene->event)
                                <span class="block text-xs text-content-subtle">{{ $scene->event->title }} &mdash; {{ $scene->event->event_datetime->format('M j, Y') }}</span>
                            @else
                                <span class="block text-xs text-content-subtle">{{ __('No event assigned') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
