@php
    use Illuminate\Support\Str;

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

<article class="bg-surface-raised shadow-xs rounded-lg overflow-hidden" aria-labelledby="save-{{ $point->saveId }}">
    <div class="border-b border-border px-6 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span id="save-{{ $point->saveId }}" class="font-medium text-content">
                {{ $point->savedAt->format('d F Y H:i') }}
            </span>
            <span class="text-content-muted">{{ $point->authorName ?? __('Unknown') }}</span>
            <x-revision-origin-badge :origin="$point->origin" />
            @if ($point->label)
                <span class="text-content-muted">{{ $point->label }}</span>
            @endif
            @if ($point->isCurrent)
                <x-badge variant="success">{{ __('Current') }}</x-badge>
            @endif
        </div>

        <div class="flex items-center gap-3">
            @if ($compareWithPrevious)
                <a href="{{ $compareWithPrevious }}" class="text-sm text-link hover:text-link-hover hover:underline">
                    {{ __('Compare with previous') }}
                </a>
            @endif

            @if (! $point->isBaseline())
                <x-undo-save-button :point="$point" :base-hashes="$baseHashes" />
            @endif
        </div>
    </div>

    <div class="px-6 py-3">
        @if ($point->isBaseline())
            <p class="text-sm text-content-muted italic">
                {{ __('Initial value — before revision history') }}
            </p>
        @else
            <dl class="space-y-2">
                @foreach ($point->entries as $entry)
                    <div class="sm:flex sm:items-baseline sm:gap-3">
                        <dt class="text-sm font-medium text-content-muted sm:w-32 sm:shrink-0">
                            {{ Str::headline($entry->field) }}
                        </dt>
                        <dd class="min-w-0 flex-1 text-sm">
                            @if ($entry->summaryHtml)
                                <x-diff :html="$entry->summaryHtml" :kind="$entry->kind" inline />
                            @else
                                <span class="text-content-subtle italic">{{ __('No summary recorded.') }}</span>
                            @endif

                            @if ($entry->hasMoreChanges() && $compareWithPrevious)
                                <a
                                    href="{{ $compareWithPrevious }}"
                                    class="text-link hover:text-link-hover hover:underline whitespace-nowrap"
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
