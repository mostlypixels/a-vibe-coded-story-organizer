@php
    use App\Support\CodexMediaRules;

    $coverUrl = $book->cover_image
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($book->cover_image)
        : null;
@endphp

<x-app-layout>
    <x-page-heading>
        {{ __('Edit Book') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="book-edit-form" method="POST" action="{{ route('books.update', $book) }}" class="space-y-6" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <x-field name="name" :label="__('Name')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $book->name)" :required="$nameRequired" autofocus />
                    @unless ($nameRequired)
                        <p class="mt-1 text-sm text-content-muted">{{ __('Optional while this is the project\'s only book — it takes the project\'s name until you give it one of its own.') }}</p>
                    @endunless
                </x-field>

                <div>
                    <x-autosave-field entity="book" :model="$book" field="description" :label="__('Description')" />
                </div>
            </form>
        </x-card>

        <x-card :title="__('Book metadata')">
            <p class="text-sm text-content-muted">{{ __('Used when exporting this book as an EPUB.') }}</p>

            <div class="mt-4 space-y-6">
                <x-field name="language" :label="__('Language')">
                    <x-select id="language" name="language" form="book-edit-form" class="mt-1 block w-full" required>
                        @foreach (\App\Enums\BookLanguage::cases() as $language)
                            <option value="{{ $language->value }}" @selected(old('language', $book->language->value) === $language->value)>{{ $language->label() }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field name="author" :label="__('Author')">
                    <x-text-input id="author" name="author" form="book-edit-form" type="text" class="mt-1 block w-full" :value="old('author', $book->author)" />
                </x-field>

                <x-field name="publisher" :label="__('Publisher')">
                    <x-text-input id="publisher" name="publisher" form="book-edit-form" type="text" class="mt-1 block w-full" :value="old('publisher', $book->publisher)" />
                </x-field>

                <x-field name="isbn" :label="__('ISBN')">
                    <x-text-input id="isbn" name="isbn" form="book-edit-form" type="text" class="mt-1 block w-full" :value="old('isbn', $book->isbn)" />
                    <p class="mt-1 text-xs text-content-subtle">{{ __('ISBN-13, with or without hyphens.') }}</p>
                </x-field>

                <div>
                    <x-autosave-field entity="book" :model="$book" field="rights" form="book-edit-form" :label="__('Rights')" />
                </div>
            </div>
        </x-card>

        <x-card :title="__('Front & back matter')">
            <p class="text-sm text-content-muted">{{ __('Optional pages placed around the manuscript in the EPUB export.') }}</p>

            <div class="mt-4 space-y-6">
                <div>
                    <x-autosave-field entity="book" :model="$book" field="dedication" form="book-edit-form" :label="__('Dedication')" />
                </div>

                <div>
                    <x-autosave-field entity="book" :model="$book" field="acknowledgements" form="book-edit-form" :label="__('Acknowledgements')" />
                </div>

                <div>
                    <x-autosave-field entity="book" :model="$book" field="preface" form="book-edit-form" :label="__('Preface')" />
                </div>

                <div>
                    <x-autosave-field entity="book" :model="$book" field="postface" form="book-edit-form" :label="__('Postface')" />
                </div>
            </div>
        </x-card>

        <x-slot:sidebar>
            @if ($isLastBook)
                <x-edit-actions form="book-edit-form" :history-model="$book" />
            @elseif ($book->acts_count > 0)
                <x-edit-actions form="book-edit-form" :history-model="$book">
                    <x-slot:delete>
                        <x-button
                            variant="danger"
                            type="button"
                            :icon="true"
                            class="w-full"
                            x-data=""
                            x-on:click="$dispatch('open-modal', 'delete-book-{{ $book->id }}')"
                        >
                            {{ __('Delete Book') }}
                        </x-button>
                    </x-slot:delete>
                </x-edit-actions>

                <x-delete-with-move-dialog
                    name="delete-book-{{ $book->id }}"
                    :action="route('books.destroy', $book)"
                    :title="__('Delete Book?')"
                    :child-count="$book->acts_count"
                    child-singular="act"
                    child-plural="acts"
                    destination-noun="book"
                    :secondary-count="$chapterCount"
                    secondary-singular="chapter"
                    secondary-plural="chapters"
                    :tertiary-count="$sceneCount"
                    tertiary-singular="scene"
                    tertiary-plural="scenes"
                    :destinations="$destinations"
                />
            @else
                <x-edit-actions
                    form="book-edit-form"
                    :history-model="$book"
                    :delete-action="route('books.destroy', $book)"
                    :delete-confirm="__('Are you sure you want to delete this book?')"
                >
                    {{ __('Delete Book') }}
                </x-edit-actions>
            @endif

            <x-card :title="$coverUrl ? __('Replace cover image') : __('Cover image')">
                <p class="text-sm text-content-muted">{{ __('The EPUB cover for this book.') }}</p>

                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ $book->displayName() }}" class="mt-3 w-full rounded-md border border-border object-cover">

                    <label class="mt-2 flex items-center gap-2 text-sm text-content-muted">
                        <input type="checkbox" name="remove_cover_image" value="1" form="book-edit-form" class="rounded-sm border-border-strong text-link focus:ring-focus">
                        {{ __('Remove cover image') }}
                    </label>
                @endif

                <input id="cover_image" name="cover_image" type="file" form="book-edit-form" accept="{{ CodexMediaRules::imageAccept() }}" class="mt-2 block w-full text-sm text-content-muted file:mr-3 file:rounded-md file:border-0 file:bg-neutral file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-neutral-content hover:file:bg-neutral/80">
                <p class="mt-1 text-xs text-content-subtle">{{ CodexMediaRules::imageHint() }}</p>
                <x-input-error :messages="$errors->get('cover_image')" class="mt-2" />
            </x-card>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>
