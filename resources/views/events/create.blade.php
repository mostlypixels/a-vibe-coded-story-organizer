<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('New Event') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form method="POST" action="{{ route('projects.events.store', $project) }}" class="space-y-6">
                @csrf

                <div>
                    <x-input-label for="title" :value="__('Title')" />
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required autofocus />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="event_datetime" :value="__('Date & Time')" />
                    <x-text-input id="event_datetime" name="event_datetime" type="datetime-local" class="mt-1 block w-full" :value="old('event_datetime')" min="{{ $windowMin }}" max="{{ $windowMax }}" required />
                    <x-input-error :messages="$errors->get('event_datetime')" class="mt-2" />
                </div>

                <div>
                    <x-input-label :value="__('Plotlines')" />
                    <div class="mt-2 space-y-2">
                        @foreach ($project->plotlines as $plotline)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="plotlines[]" value="{{ $plotline->id }}" @checked(in_array($plotline->id, old('plotlines', [])))>
                                <span>{{ $plotline->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('plotlines')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-button variant="primary">{{ __('Create Event') }}</x-button>
                </div>
            </form>
        </x-card>
    </x-edit-layout>
</x-app-layout>
