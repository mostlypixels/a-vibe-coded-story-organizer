@props(['project', 'books', 'book', 'chapters', 'scope'])

@php
    $numbering = \App\Support\StoryNumbering::fromChapters($chapters);
@endphp

<x-collapsible-card :open="$scope->isNarrowed()">
    <x-slot name="header">
        <x-heading level="3" class="inline">{{ __('Narrow') }}</x-heading>
    </x-slot>

    <div class="space-y-6">
        <div class="space-y-1">
            @if ($books->count() > 1)
                <x-input-label for="narrow-book" :value="__('Book')" />
                <x-select id="narrow-book" name="book" class="block w-full sm:w-auto">
                    <option value="">{{ __('— Whole project —') }}</option>
                    @foreach ($books as $candidate)
                        <option value="{{ $candidate->id }}" @selected($book?->id === $candidate->id)>
                            {{ $candidate->displayName() }}
                        </option>
                    @endforeach
                </x-select>
            @elseif ($book)
                {{-- Only one book exists: it filters every search, with no control to show for it. --}}
                <input type="hidden" name="book" value="{{ $book->id }}" />
            @endif
        </div>

        <div class="space-y-1">
            <span class="block font-medium text-sm text-content-muted">{{ __('Chapter range') }}</span>

            @if ($book)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="narrow-from-chapter" :value="__('From')" />
                        <x-select id="narrow-from-chapter" name="from_chapter" class="mt-1 block w-full">
                            <option value="">{{ __('— Start —') }}</option>
                            @foreach ($chapters as $chapter)
                                <option value="{{ $chapter->id }}" @selected($scope->fromChapterId === $chapter->id)>
                                    {{ $numbering->chapter($chapter) }}. {{ $chapter->name }}
                                </option>
                            @endforeach
                        </x-select>
                    </div>

                    <div>
                        <x-input-label for="narrow-to-chapter" :value="__('To')" />
                        <x-select id="narrow-to-chapter" name="to_chapter" class="mt-1 block w-full">
                            <option value="">{{ __('— End —') }}</option>
                            @foreach ($chapters as $chapter)
                                <option value="{{ $chapter->id }}" @selected($scope->toChapterId === $chapter->id)>
                                    {{ $numbering->chapter($chapter) }}. {{ $chapter->name }}
                                </option>
                            @endforeach
                        </x-select>
                    </div>
                </div>
            @else
                <p class="text-sm text-content-muted">{{ __('Choose a book first to narrow by chapter range.') }}</p>
            @endif
        </div>

        <div class="space-y-4">
            <span class="block font-medium text-sm text-content-muted">{{ __('Domains') }}</span>

            @foreach (\App\Enums\SearchSection::cases() as $section)
                <div x-data class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-content">{{ $section->label() }}</span>
                        <label class="inline-flex items-center gap-1 text-xs text-content-muted">
                            <input
                                type="checkbox"
                                @change="$refs.group.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = $event.target.checked)"
                                class="rounded border-border-strong text-primary focus:ring-focus"
                            />
                            {{ __('All') }}
                        </label>
                    </div>
                    <div x-ref="group" class="flex flex-wrap gap-x-4 gap-y-2">
                        @foreach ($section->domains() as $domain)
                            <label class="inline-flex items-center gap-2 text-sm text-content-muted">
                                <input
                                    type="checkbox"
                                    name="domains[]"
                                    value="{{ $domain->value }}"
                                    @checked(in_array($domain, $scope->domains, true))
                                    class="rounded border-border-strong text-primary focus:ring-focus"
                                />
                                {{ $domain->label() }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-collapsible-card>
