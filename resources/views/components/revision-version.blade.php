@props([
    'revision' => null,
    'kind',
    'label',
    'baseHash',
    'isCurrent' => false,
])

<x-revision-panel :title="$label">
    @if ($revision !== null)
        <x-slot name="badge">
            <x-revision-origin-badge :origin="$revision->origin" />
        </x-slot>

        <x-slot name="meta">
            {{ $revision->created_at->format('d F Y H:i') }}
            @if ($revision->user !== null)
                &middot; {{ $revision->user->name }}
            @endif
        </x-slot>
    @endif

    @if ($revision === null)
        <p class="text-sm italic text-content-muted">{{ __('This field had no content yet.') }}</p>
    @elseif (blank($revision->value))
        <p class="text-sm italic text-content-muted">{{ __('Empty.') }}</p>
    @elseif ($kind === \App\Enums\FieldKind::Rich)
        <x-rich-text :html="$revision->value" />
    @elseif ($kind === \App\Enums\FieldKind::Markdown)
        <div class="prose prose-sm max-w-none text-content-muted">{!! \Illuminate\Support\Str::markdown($revision->value) !!}</div>
    @else
        <p class="whitespace-pre-wrap text-sm text-content-muted">{{ $revision->value }}</p>
    @endif

    <x-slot name="footer">
        @if ($revision === null)
            <p class="text-xs text-content-subtle">&nbsp;</p>
        @elseif ($isCurrent)
            <x-badge variant="success">{{ __('Current version') }}</x-badge>
        @else
            <x-revert-revision-button :revision="$revision" :base-hash="$baseHash" />
        @endif
    </x-slot>
</x-revision-panel>
