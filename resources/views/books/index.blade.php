@php
    // A project always holds at least one book (Project::booted()) — the last
    // one gets no delete control at all, the same belt-and-braces the main
    // plotline gets.
    $isLastBook = $books->count() === 1;
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
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-content-muted">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('books.edit', $book) }}" class="font-semibold text-content hover:text-link">{{ $book->displayName() }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm text-content-muted">{{ $book->acts_count }}</td>
                        <td class="px-4 py-3 text-right text-sm text-content-muted whitespace-nowrap">
                            <x-word-count :count="$book->word_count" variant="inline" />
                        </td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-move-button direction="up" :action="route('books.move-up', $book)" :disabled="$loop->first" />
                                <x-icon-move-button direction="down" :action="route('books.move-down', $book)" :disabled="$loop->last" />
                                <x-icon-edit-link :href="route('books.edit', $book)" />
                                @unless ($isLastBook)
                                    @if ($book->acts_count > 0)
                                        <x-icon-dialog-button :modal="'delete-book-'.$book->id" />
                                    @else
                                        <x-icon-delete-button :action="route('books.destroy', $book)" :confirm="__('Are you sure you want to delete this book?')" />
                                    @endif
                                @endunless
                            </div>
                        </td>
                    </x-table-row>
                @endforeach
            </x-table>

            {{-- Delete-with-move dialogs live outside the table (a modal <div> is not
                 valid inside a <tbody>); each is opened by its row's trash button. --}}
            @unless ($isLastBook)
                @foreach ($books as $book)
                    @if ($book->acts_count > 0)
                        <x-delete-with-move-dialog
                            name="delete-book-{{ $book->id }}"
                            :action="route('books.destroy', $book)"
                            :title="__('Delete Book?')"
                            :child-count="$book->acts_count"
                            child-singular="act"
                            child-plural="acts"
                            destination-noun="book"
                            :destinations="$destinationBooks->where('id', '!=', $book->id)->values()"
                        />
                    @endif
                @endforeach
            @endunless
    </div>
</x-app-layout>
