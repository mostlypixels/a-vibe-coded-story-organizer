<x-app-layout>
    <x-page-heading>
        {{ __('New Chapter') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="chapter-create-form" method="POST" action="{{ route('books.chapters.store', $book) }}" class="space-y-6">
                @csrf

                <x-field name="act_id" :label="__('Act')">
                    <x-select id="act_id" name="act_id" class="mt-1 block w-full" required>
                        <option value="">{{ __('Select an act...') }}</option>
                        @foreach ($acts as $act)
                            <option value="{{ $act->id }}" @selected(old('act_id') == $act->id)>{{ $act->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="name" :label="__('Title')" :hint="__('The chapter number is assigned automatically and can be changed later by reordering.')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="{{ __('e.g. The Oath at the Fountain') }}" required autofocus />
                </x-field>

                <x-field name="description" :label="__('Description')">
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                </x-field>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="chapter-create-form" :cancel="route('books.chapters.index', $book)">
                {{ __('Create Chapter') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>
