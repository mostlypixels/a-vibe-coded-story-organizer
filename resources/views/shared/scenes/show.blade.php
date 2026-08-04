<x-public-layout>
    <div class="max-w-3xl mx-auto px-4 py-10 space-y-6">
        {{-- Formatted title: "Chapter 1 — Chapter title: Scene title".
             Continuous, project-wide chapter number, matching the Story overview. --}}
        <x-heading level="1">
            {{ __('Chapter :number', ['number' => $numbering->chapter($scene->chapter)]) }}
            &mdash; {{ $scene->chapter->name }}: {{ $scene->name }}
        </x-heading>

        {{-- Description in a COLLAPSED card (starts closed, per spec). The body
             is already-sanitized rich HTML, rendered only via x-rich-text. --}}
        @if (filled($scene->description))
            <div x-data="{ open: false }" class="bg-surface-raised shadow-xs rounded-lg">
                <button type="button" @click="open = ! open"
                        class="w-full flex items-center justify-between px-6 py-4 text-left">
                    <span class="font-semibold text-content">{{ __('Description') }}</span>
                    <x-tabler-chevron-down class="h-4 w-4 text-content-muted transition-transform" x-bind:class="{ 'rotate-180': open }" />
                </button>
                <div x-show="open" x-transition class="px-6 pb-4">
                    <x-rich-text :html="$scene->description" />
                </div>
            </div>
        @endif

        {{-- Contents rendered as formatted HTML (Markdown → HTML) via the single
             Scene::renderedContents accessor, the same render path as the Story
             overview and the book export. `notes` is NEVER rendered here. --}}
        <article class="prose prose-sm max-w-none text-content-muted text-justify [&_p]:my-4">
            {!! $scene->renderedContents !!}
        </article>
    </div>
</x-public-layout>
