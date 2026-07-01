<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Scene') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('projects.scenes.store', $project) }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="chapter_id" :value="__('Chapter')" />
                            <select id="chapter_id" name="chapter_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                <option value="">{{ __('Select a chapter...') }}</option>
                                @foreach ($chapters as $chapter)
                                    <option value="{{ $chapter->id }}" @selected(old('chapter_id') == $chapter->id)>{{ $chapter->act->name }} &mdash; {{ $chapter->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('chapter_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="name" :value="__('Title')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="{{ __('e.g. A Lady at the Fountain') }}" required autofocus />
                            <p class="mt-1 text-sm text-gray-500">{{ __('The scene number is assigned automatically and can be changed later by reordering.') }}</p>
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="description" :value="__('Description')" />
                            <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="contents" :value="__('Contents (Markdown)')" />
                            <textarea id="contents" name="contents" rows="12" class="mt-1 block w-full font-mono text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('contents') }}</textarea>
                            <x-input-error :messages="$errors->get('contents')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Create Scene') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
