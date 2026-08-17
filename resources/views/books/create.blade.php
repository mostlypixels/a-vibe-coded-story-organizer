<x-app-layout>
    <x-page-heading>
        {{ __('New Book') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="book-create-form" method="POST" action="{{ route('projects.books.store', $project) }}" class="space-y-6">
                @csrf

                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" :required="$nameRequired" autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="book-create-form" :cancel="route('projects.books.index', $project)">
                {{ __('Create Book') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>
