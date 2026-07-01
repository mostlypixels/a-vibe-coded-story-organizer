<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Event') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
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
                            <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $event->description) }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="event_datetime" :value="__('Date & Time')" />
                            <x-text-input id="event_datetime" name="event_datetime" type="datetime-local" class="mt-1 block w-full" :value="old('event_datetime', $event->event_datetime->format('Y-m-d\TH:i'))" required />
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
                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('events.destroy', $event) }}" class="mt-6" onsubmit="return confirm('{{ __('Are you sure you want to delete this event?') }}')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>{{ __('Delete Event') }}</x-danger-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
