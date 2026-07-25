<x-revisions-layout :project="$project" :entity="$entity" :id="$id" :field="$field">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <x-heading level="2">
                {{ __('Compare') }} &mdash; {{ Illuminate\Support\Str::headline($entity) }} "{{ $entityName }}" &mdash; {{ Illuminate\Support\Str::headline($field) }}
            </x-heading>
            <a href="{{ route('revisions.index', ['entity' => $entity, 'id' => $id, 'field' => $field]) }}" class="text-sm">
                {{ __('Back to history') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($from === null || $to === null)
            {{-- Not enough history yet to compare (fewer than two revisions), or an
                 explicit from/to pair that failed to resolve. --}}
            <div class="bg-white shadow-sm rounded-lg px-6 py-10 text-center text-gray-500">
                <p class="font-medium text-gray-600">{{ __('Nothing to compare yet.') }}</p>
                <p class="mt-1 text-sm">{{ __('This field needs at least two revisions before they can be compared.') }}</p>
            </div>
        @else
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                {{-- Old / New header, aligned to the 50/50 diff columns below (both
                     this row and the table are full-width halves), so the labels sit
                     directly over their column. $from is the older revision, $to the
                     newer — RevisionController::compare() already ordered them
                     chronologically regardless of the query string's from/to. --}}
                <div class="grid grid-cols-2 border-b border-gray-200 bg-gray-50 text-sm text-gray-600">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-e border-gray-200">
                        <div>
                            <span class="font-semibold text-gray-800">{{ __('Old') }}</span>
                            &mdash; {{ $from->created_at->format('d F Y H:i') }}
                            ({{ $from->user?->name ?? __('Unknown') }})
                        </div>
                        <x-revert-revision-button :revision="$from" :base-hash="$baseHash" />
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <span class="font-semibold text-gray-800">{{ __('New') }}</span>
                            &mdash; {{ $to->created_at->format('d F Y H:i') }}
                            ({{ $to->user?->name ?? __('Unknown') }})
                        </div>
                        <x-revert-revision-button :revision="$to" :base-hash="$baseHash" />
                    </div>
                </div>

                {{-- All diff styling lives in <x-diff>; this page declares none of
                     its own, so a rich field's visual diff and a Markdown field's
                     side-by-side table read as one feature. --}}
                <x-diff :html="$result->html" :kind="$kind" class="px-4 py-3" />
            </div>
        @endif
    </div>
</x-revisions-layout>
