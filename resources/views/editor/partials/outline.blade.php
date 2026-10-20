{{--
    Left panel: the outline of the current book, in story order.
    Only the current chapter starts open. Fold state is not saved.
    Rows are real links, so the leave guard flushes a pending save first.
--}}
@php
    $numbering = $outline->numbering();
    $showActs = ! $outline->hasSingleAct();
@endphp

<nav
    x-show="showingBrowse()"
    aria-label="{{ __('Outline') }}"
    class="rounded-md border border-border bg-surface shadow-xs"
    data-editor-outline
>
    <div class="m-1.5 px-1 py-0.5">
        <h3 class="text-sm font-semibold text-content">{{ __('Outline') }}</h3>
    </div>

    <div class="space-y-3 border-t border-border p-1.5">
        @foreach ($outline->acts() as $act)
            <div class="space-y-1">
                @if ($showActs)
                    <h4 class="truncate text-xs font-semibold uppercase tracking-wide text-content-subtle" title="{{ $numbering->actLabel($act) }}">{{ $numbering->actLabel($act) }}</h4>
                @endif

                @foreach ($act->chapters as $chapter)
                    @php
                        $chapterLabel = $numbering->chapterLabel($chapter);
                    @endphp
                    <details class="group/chapter" @if ($chapter->id === $currentChapterId) open @endif>
                        <summary class="flex cursor-pointer select-none list-none items-center gap-1 rounded-sm px-1 py-1 text-sm hover:bg-neutral [&::-webkit-details-marker]:hidden">
                            <x-tabler-chevron-down class="h-3.5 w-3.5 shrink-0 -rotate-90 text-content-subtle transition-transform group-open/chapter:rotate-0" aria-hidden="true" />
                            <span class="min-w-0 flex-1 truncate font-medium text-content" title="{{ $chapterLabel }}">{{ $chapterLabel }}</span>
                        </summary>

                        @if ($chapter->scenes->isEmpty())
                            <p class="py-1 ps-6 text-sm text-content-muted">{{ __('No scenes yet.') }}</p>
                        @else
                            <ul class="ps-3.5">
                                @foreach ($chapter->scenes as $item)
                                    @php
                                        $isCurrent = $item->id === $scene->id;
                                    @endphp
                                    <li>
                                        <a
                                            href="{{ route('scenes.write', $item) }}"
                                            title="{{ $item->name }}"
                                            @if ($isCurrent) aria-current="page" @endif
                                            @class([
                                                'block truncate rounded-sm px-2 py-1 text-sm hover:bg-neutral',
                                                'text-content' => ! $isCurrent,
                                                'bg-accent-surface text-accent-content' => $isCurrent,
                                            ])
                                        >{{ $item->name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </details>
                @endforeach
            </div>
        @endforeach
    </div>
</nav>
