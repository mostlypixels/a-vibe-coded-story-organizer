<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Event') }}
        </h2>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form method="POST" action="{{ route('events.update', $event) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="title" :value="__('Title')" />
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $event->title)" required autofocus />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description', $event->description)" :rows="4" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="event_datetime" :value="__('Date & Time')" />
                    <x-text-input id="event_datetime" name="event_datetime" type="datetime-local" class="mt-1 block w-full" :value="old('event_datetime', $event->event_datetime->format('Y-m-d\TH:i'))" min="{{ $windowMin }}" max="{{ $windowMax }}" required />
                    @if ($event->is_fixed)
                        <p class="mt-1 text-xs text-gray-500">{{ __('This bookend anchors the timeline; every other event must stay between Start and End.') }}</p>
                    @endif
                    <x-input-error :messages="$errors->get('event_datetime')" class="mt-2" />
                </div>

                <div>
                    <x-input-label :value="__('Plotlines')" />
                    <div class="mt-2 space-y-2">
                        @foreach ($project->plotlines as $plotline)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="plotlines[]" value="{{ $plotline->id }}" @checked(in_array($plotline->id, old('plotlines', $event->plotlines->pluck('id')->all())))>
                                <span>{{ $plotline->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('plotlines')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button :icon="true">{{ __('Save') }}</x-primary-button>
                </div>
            </form>

            @unless ($event->is_fixed)
                <form method="POST" action="{{ route('events.destroy', $event) }}" class="mt-6" onsubmit="return confirm('{{ __('Are you sure you want to delete this event?') }}')">
                    @csrf
                    @method('DELETE')
                    <x-danger-button :icon="true">{{ __('Delete Event') }}</x-danger-button>
                </form>
            @endunless
        </x-card>

        <x-slot:sidebar>
            <x-card>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('Scenes happening during') }}</h3>
                    @forelse ($event->scenes as $scene)
                        <a href="{{ route('scenes.edit', $scene) }}" class="mt-1 block text-sm text-ocean-600 hover:text-ocean-800">{{ $scene->name }}</a>
                    @empty
                        <p class="mt-1 text-sm text-gray-500">{{ __('None yet.') }}</p>
                    @endforelse
                </div>

                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('Mentioned in scenes') }}</h3>
                    @forelse ($event->mentioningScenes as $scene)
                        <a href="{{ route('scenes.edit', $scene) }}" class="mt-1 block text-sm text-ocean-600 hover:text-ocean-800">{{ $scene->name }}</a>
                    @empty
                        <p class="mt-1 text-sm text-gray-500">{{ __('None yet.') }}</p>
                    @endforelse
                </div>
            </x-card>

            @include('codex.partials.as-of', [
                'title' => __('Values as of this event'),
                'moment' => $event,
                'groups' => $codexAsOfGroups,
            ])
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>
