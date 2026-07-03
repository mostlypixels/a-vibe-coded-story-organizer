<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Scene') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                    <form method="POST" action="{{ route('scenes.update', $scene) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="chapter_id" :value="__('Chapter')" />
                            <select id="chapter_id" name="chapter_id" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm" required>
                                @foreach ($chapters as $chapter)
                                    <option value="{{ $chapter->id }}" @selected(old('chapter_id', $scene->chapter_id) == $chapter->id)>{{ $chapter->act->name }} &mdash; {{ $chapter->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('chapter_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="name" :value="__('Title')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $scene->name)" placeholder="{{ __('e.g. A Lady at the Fountain') }}" required autofocus />
                            <p class="mt-1 text-sm text-gray-500">{{ __('Currently scene #:position within its chapter. Use the move up/down buttons on the list to reorder.', ['position' => $scene->position]) }}</p>
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm" required>
                                @foreach (\App\Enums\SceneStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected(old('status', $scene->status->value) === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="description" :value="__('Description')" />
                            <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">{{ old('description', $scene->description) }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="contents" :value="__('Contents (Markdown)')" />
                            <textarea id="contents" name="contents" rows="12" class="mt-1 block w-full font-mono text-sm border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">{{ old('contents', $scene->contents) }}</textarea>
                            <x-input-error :messages="$errors->get('contents')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="notes" :value="__('Notes (Markdown)')" />
                            <textarea id="notes" name="notes" rows="6" class="mt-1 block w-full font-mono text-sm border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm">{{ old('notes', $scene->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('scenes.destroy', $scene) }}" class="mt-6" onsubmit="return confirm('{{ __('Are you sure you want to delete this scene?') }}')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>{{ __('Delete Scene') }}</x-danger-button>
                    </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
