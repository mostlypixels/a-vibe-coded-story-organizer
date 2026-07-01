<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Chapter') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('chapters.update', $chapter) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="act_id" :value="__('Act')" />
                            <select id="act_id" name="act_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                @foreach ($project->acts as $act)
                                    <option value="{{ $act->id }}" @selected(old('act_id', $chapter->act_id) == $act->id)>{{ $act->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('act_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="name" :value="__('Title')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $chapter->name)" placeholder="{{ __('e.g. The Oath at the Fountain') }}" required autofocus />
                            <p class="mt-1 text-sm text-gray-500">{{ __('Currently chapter #:position within its act. Use the move up/down buttons on the list to reorder.', ['position' => $chapter->position]) }}</p>
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="description" :value="__('Description')" />
                            <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $chapter->description) }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('chapters.destroy', $chapter) }}" class="mt-6" onsubmit="return confirm('{{ __('Are you sure you want to delete this chapter?') }}')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>{{ __('Delete Chapter') }}</x-danger-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
