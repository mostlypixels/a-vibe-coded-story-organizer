@props([
    'revision' => null,
    'kind',
    'label',
    'baseHash',
    'isCurrent' => false,
])

{{--
    One side of the compare page's before/after pair: a whole field value as it
    stood at one save point, with the action that puts it back underneath it.

    ## Why the whole value, next to a diff that already says what changed

    A diff answers "what moved"; it does not answer "what would I be looking at
    if I took this one". "Revert to this" is a choice between two *versions*, so
    both versions have to be readable as versions — and the button belongs under
    the text it restores, not floating in a card header where it points at
    neither column.

    The value renders the way the app renders that field everywhere else (see
    the kind branches below), so what a reader compares here is what they will
    get back.

    The pane itself — border, header, scrolling body, footer — is
    <x-revision-panel>, shared with the diff above it.
--}}
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
        {{-- The field had no revision at this point: everything on the other
             side is an insertion, and there is nothing here to restore. --}}
        <p class="text-sm italic text-gray-500">{{ __('This field had no content yet.') }}</p>
    @elseif (blank($revision->value))
        <p class="text-sm italic text-gray-500">{{ __('Empty.') }}</p>
    @elseif ($kind === \App\Enums\FieldKind::Rich)
        {{-- Sanitized on write, so it goes through the one component allowed
             to echo rich author HTML. --}}
        <x-rich-text :html="$revision->value" />
    @elseif ($kind === \App\Enums\FieldKind::Markdown)
        {{-- Markdown → HTML at read time, exactly like Scene::renderedContents
             and the book export do for the live value: a Markdown field is
             never sanitized, it is gated by the ValidMarkdown rule on write
             (and again by RevisionReverter on restore). Rendering the stored
             source is what makes this column show the same thing the writer
             sees in the app rather than its source. --}}
        <div class="prose prose-sm max-w-none text-gray-700">{!! \Illuminate\Support\Str::markdown($revision->value) !!}</div>
    @else
        {{-- Plain text has no markup at all: escaped, with its own line
             breaks kept. --}}
        <p class="whitespace-pre-wrap text-sm text-gray-700">{{ $revision->value }}</p>
    @endif

    <x-slot name="footer">
        @if ($revision === null)
            {{-- Nothing to revert to. The empty footer keeps the two columns the
                 same shape, so the other side's button doesn't drift upward. --}}
            <p class="text-xs text-gray-400">&nbsp;</p>
        @elseif ($isCurrent)
            {{-- Restoring the value already in the column is a no-op dressed up
                 as an action: say so instead of offering it. --}}
            <x-badge variant="success">{{ __('Current version') }}</x-badge>
        @else
            <x-revert-revision-button :revision="$revision" :base-hash="$baseHash" />
        @endif
    </x-slot>
</x-revision-panel>
