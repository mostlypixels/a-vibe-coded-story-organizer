@php
    // total(), not count(): the project has one book, not one book on this page.
    $isLastBook = $books->total() === 1;
@endphp

<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __('Books') }}
    </x-page-heading>

    <div class="space-y-6">
            <div class="flex items-center justify-end">
                <x-button variant="primary" :href="route('projects.books.create', $project)">{{ __('New Book') }}</x-button>
            </div>

            <x-table>
                <x-slot:head>
                    <x-table-heading>{{ __('#') }}</x-table-heading>
                    <x-table-heading>{{ __('Name') }}</x-table-heading>
                    <x-table-heading>{{ __('Acts') }}</x-table-heading>
                    <x-table-heading class="text-right">{{ __('Words') }}</x-table-heading>
                    <x-table-heading />
                </x-slot:head>

                @foreach ($books as $book)
                    <x-table-row :striped="$loop->even">
                        <x-table-cell muted nowrap>{{ $books->firstItem() + $loop->index }}</x-table-cell>
                        <x-table-cell>
                            <a href="{{ route('books.edit', $book) }}" class="font-semibold text-content hover:text-link">{{ $book->displayName() }}</a>
                        </x-table-cell>
                        <x-table-cell muted>{{ $book->acts_count }}</x-table-cell>
                        <x-table-cell align="right" muted nowrap>
                            <x-word-count :count="$book->word_count" variant="inline" />
                        </x-table-cell>
                        <x-table-cell align="right" nowrap sm>
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-move-button direction="up" :action="route('books.move-up', $book)" :disabled="$loop->first && $books->onFirstPage()" />
                                <x-icon-move-button direction="down" :action="route('books.move-down', $book)" :disabled="$loop->last && $books->onLastPage()" />
                                <x-icon-edit-link :href="route('books.edit', $book)" />
                                @unless ($isLastBook)
                                    @if ($book->acts_count > 0)
                                        <x-icon-delete-with-move-button dialog="delete-book" :action="route('books.destroy', $book)" :exclude="$book->id" :child-count="$book->acts_count" child-singular="act" child-plural="acts" />
                                    @else
                                        <x-icon-delete-button :action="route('books.destroy', $book)" :confirm="__('Are you sure you want to delete this book?')" />
                                    @endif
                                @endunless
                            </div>
                        </x-table-cell>
                    </x-table-row>
                @endforeach
            </x-table>

            <x-pagination-bar :paginator="$books" />

            @unless ($isLastBook)
                @if ($books->contains(fn ($row) => $row->acts_count > 0))
                    <x-delete-with-move-list-dialog
                        name="delete-book"
                        :title="__('Delete Book?')"
                        destination-noun="book"
                        :destinations="$destinationBooks"
                    />
                @endif
            @endunless
    </div>
</x-app-layout>
