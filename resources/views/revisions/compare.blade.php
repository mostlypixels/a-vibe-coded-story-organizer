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

                @if ($result->formattingChangedOnly)
                    {{-- handoff.md §5.3: same prose, different HTML markup only —
                         rendering an empty diff here would misleadingly read as
                         "nothing changed". --}}
                    <p class="text-gray-600 italic p-6">{{ __('Formatting changed only.') }}</p>
                @else
                    {{-- jfcherng/php-diff's HTML renderer already escapes the
                         underlying text — safe to render directly. The SideBySide
                         renderer emits a two-column <table> (one <td class="old">/
                         <td class="new"> pair per row, no header/line numbers per
                         RevisionDiffer's options), with word-level changes marked by
                         nested <ins>/<del>.

                         Styled to read as two prose panels rather than a spreadsheet:
                         no per-cell borders (just a single divider between the two
                         columns), and only the actually-changed words are tinted —
                         red <del> on the old side, green <ins> on the new. Empty
                         counterpart cells (`td.none`, one side of an add/remove) get a
                         faint grey so they read as "nothing here". --}}
                    <div class="overflow-x-auto text-sm leading-relaxed
                        [&_table]:w-full [&_table]:table-fixed [&_table]:border-collapse
                        [&_td]:w-1/2 [&_td]:align-top [&_td]:px-4 [&_td]:py-2 [&_td]:whitespace-pre-wrap [&_td]:break-words
                        [&_td.old]:border-e [&_td.old]:border-gray-200
                        [&_td.none]:bg-gray-50/60
                        [&_del]:bg-red-100 [&_del]:text-red-700 [&_del]:no-underline
                        [&_ins]:bg-green-100 [&_ins]:text-green-700 [&_ins]:no-underline">
                        {!! $result->html !!}
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-revisions-layout>
