{{--
    One read-only card in the right panel of the Write page. `editorCards` handles its buttons.
    The section also renders an empty copy in a <template>, and the script fills it for a new card.
--}}
@props(['type', 'id' => '', 'title' => '', 'state' => 'expanded', 'viewUrl' => '#'])

@php
    $expanded = $state === 'expanded';
    $bodyId = 'editor-card-'.$type.'-'.$id;
@endphp

<article
    class="rounded-md border border-border bg-surface shadow-xs"
    data-editor-card
    data-card-type="{{ $type }}"
    data-card-id="{{ $id }}"
    data-state="{{ $state }}"
>
    <header class="flex items-center gap-1 p-1.5">
        <button
            type="button"
            class="group flex min-w-0 flex-1 items-center gap-1.5 rounded-sm px-1 py-0.5 text-start text-sm font-semibold text-content hover:bg-neutral"
            aria-expanded="{{ $expanded ? 'true' : 'false' }}"
            aria-controls="{{ $bodyId }}"
            data-card-action="toggle"
        >
            <x-tabler-chevron-down class="h-4 w-4 shrink-0 text-content-muted -rotate-90 transition-transform group-aria-expanded:rotate-0" aria-hidden="true" />
            <span class="truncate" data-card-title>{{ $title }}</span>
        </button>

        <x-icon-view-link
            :href="$viewUrl"
            variant="ghost"
            target="_blank"
            rel="noopener"
            :label="__('Open :name in a new tab', ['name' => $title])"
            data-card-view
        />
        <x-icon-close-button
            variant="ghost"
            :label="__('Close :name', ['name' => $title])"
            data-card-action="close"
        />
    </header>

    <div id="{{ $bodyId }}" class="border-t border-border p-3" data-card-body @unless ($expanded) hidden @endunless>
        {{ $slot }}
    </div>
</article>
