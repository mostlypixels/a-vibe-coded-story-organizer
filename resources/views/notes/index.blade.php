@php
    // A number or "none" keeps the filter through search, sorting and the clear link.
    $createUrl = route('projects.notes.create', array_filter(['project' => $project, 'category' => ctype_digit((string) $category) ? $category : null]));
    $clearUrl = route('projects.notes.index', array_filter(['project' => $project, 'category' => $category]));
@endphp

<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __('Notes') }}
    </x-page-heading>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
        <aside class="md:col-span-1">
            @include('notes.partials.category-tree')
        </aside>

        <div class="space-y-6 md:col-span-3">
            <x-index-toolbar
                :sort="$sort"
                :direction="$direction"
                :search-placeholder="__('Search by title...')"
                :clear-url="$clearUrl"
                :create-url="$createUrl"
                :create-label="__('New Note')"
                :filters="['search', 'linked', 'book']"
            >
                @if ($category !== null)
                    <input type="hidden" name="category" value="{{ $category }}">
                @endif

                <x-select name="linked" class="text-sm" aria-label="{{ __('Linked to') }}">
                    <option value="">{{ __('Any link') }}</option>
                    @foreach (\App\Enums\NoteLinkType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(request('linked') === $type->value)>{{ __($type->label()) }}</option>
                    @endforeach
                </x-select>

                @if ($books->count() > 1)
                    <x-select name="book" class="text-sm" aria-label="{{ __('Book') }}">
                        <option value="">{{ __('All books') }}</option>
                        @foreach ($books as $book)
                            <option value="{{ $book->id }}" @selected(request('book') == $book->id)>{{ $book->displayName() }}</option>
                        @endforeach
                    </x-select>
                @endif
            </x-index-toolbar>

            <x-table>
                <x-slot:head>
                    <x-sortable-header field="title" :sort="$sort" :direction="$direction">{{ __('Title') }}</x-sortable-header>
                    <x-table-heading>{{ __('Category') }}</x-table-heading>
                    <x-table-heading>{{ __('Links') }}</x-table-heading>
                    <x-sortable-header field="updated_at" :sort="$sort" :direction="$direction">{{ __('Updated') }}</x-sortable-header>
                    <x-table-heading />
                </x-slot:head>

                @forelse ($notes as $note)
                    <x-table-row :striped="$loop->even">
                        <x-table-cell>
                            <a href="{{ route('notes.show', $note) }}" class="font-semibold text-content hover:text-link">{{ $note->title }}</a>
                            @if ($note->body)
                                <div class="mt-1 text-sm text-content-muted"><x-rich-text-excerpt :html="$note->body" /></div>
                            @endif
                        </x-table-cell>
                        <x-table-cell muted>{{ $note->category?->name }}</x-table-cell>
                        <x-table-cell muted>{{ $note->links_count }}</x-table-cell>
                        <x-table-cell muted nowrap><x-date :value="$note->updated_at" with-time /></x-table-cell>
                        <x-table-cell align="right" nowrap sm>
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-view-link :href="route('notes.show', $note)" />
                                <x-icon-edit-link :href="route('notes.edit', $note)" />
                                <x-icon-delete-button :action="route('notes.destroy', $note)" :confirm="__('Are you sure you want to delete this note?')" />
                            </div>
                        </x-table-cell>
                    </x-table-row>
                @empty
                    <x-table-empty
                        :colspan="5"
                        :filtered="request()->filled('search') || request()->filled('linked') || request()->filled('book') || $category !== null"
                        :create-url="$createUrl"
                        :create-label="__('New Note')"
                        :items="__('notes')"
                    />
                @endforelse
            </x-table>

            <x-pagination-bar :paginator="$notes" />
        </div>
    </div>
</x-app-layout>
