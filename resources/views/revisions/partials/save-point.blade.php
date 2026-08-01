@php
    use Illuminate\Support\Str;

    // "Compare with previous" and every per-field "and N more changes" link
    // resolve to the same pair, so the URL is built once here. The previous
    // save point comes from the boundary group RevisionHistory fetches beyond
    // the page, which is why the last row of a page still has a working link.
    $compareWithPrevious = $point->hasPrevious()
        ? route('revisions.compare', array_filter([
            'entity' => $entity,
            'id' => $id,
            'from' => $point->previousSaveId,
            'to' => $point->saveId,
            'field' => $field,
        ]))
        : null;
@endphp

{{--
    One save point: everything one Save wrote, as the single event the writer
    remembers making.

    Two levels — the save itself, then the fields it touched — which is why the
    history list is a <ul> of these rather than a table. See App\Support\SavePoint.
--}}
<article class="bg-white shadow-2xs rounded-lg overflow-hidden" aria-labelledby="save-{{ $point->saveId }}">
    <div class="border-b border-gray-200 px-6 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span id="save-{{ $point->saveId }}" class="font-medium text-gray-800">
                {{ $point->savedAt->format('d F Y H:i') }}
            </span>
            <span class="text-gray-500">{{ $point->authorName ?? __('Unknown') }}</span>
            <x-revision-origin-badge :origin="$point->origin" />
            @if ($point->label)
                <span class="text-gray-600">{{ $point->label }}</span>
            @endif
            @if ($point->isCurrent)
                <x-badge variant="success">{{ __('Current') }}</x-badge>
            @endif
        </div>

        <div class="flex items-center gap-3">
            @if ($compareWithPrevious)
                <a href="{{ $compareWithPrevious }}" class="text-sm text-ocean-600 hover:text-ocean-800 hover:underline">
                    {{ __('Compare with previous') }}
                </a>
            @endif

            {{-- Offered on every save point *including the current one* —
                 undoing the newest save is the most useful case, since undo
                 restores what came before it. The exception is a baseline: it is
                 the seeded pre-history value and has nothing before it to go
                 back to. The endpoint refuses that too — a hidden button is not
                 a check. --}}
            @if (! $point->isBaseline())
                <x-undo-save-button :point="$point" :base-hashes="$baseHashes" />
            @endif
        </div>
    </div>

    <div class="px-6 py-3">
        @if ($point->isBaseline())
            {{-- A baseline's created_at is borrowed from the entity's own
                 updated_at at seeding time, so it must never be read as an edit
                 someone made. --}}
            <p class="text-sm text-gray-500 italic">
                {{ __('Initial value — before revision history') }}
            </p>
        @else
            <dl class="space-y-2">
                @foreach ($point->entries as $entry)
                    <div class="sm:flex sm:items-baseline sm:gap-3">
                        <dt class="text-sm font-medium text-gray-600 sm:w-32 sm:shrink-0">
                            {{ Str::headline($entry->field) }}
                        </dt>
                        <dd class="min-w-0 flex-1 text-sm">
                            @if ($entry->summaryHtml)
                                <x-diff :html="$entry->summaryHtml" :kind="$entry->kind" inline />
                            @else
                                <span class="text-gray-400 italic">{{ __('No summary recorded.') }}</span>
                            @endif

                            @if ($entry->hasMoreChanges() && $compareWithPrevious)
                                <a
                                    href="{{ $compareWithPrevious }}"
                                    class="text-ocean-600 hover:text-ocean-800 hover:underline whitespace-nowrap"
                                >{{ trans_choice(
                                    '{1}and 1 more change|[2,*]and :count more changes',
                                    $entry->otherChangeCount(),
                                    ['count' => $entry->otherChangeCount()],
                                ) }}</a>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>
</article>
