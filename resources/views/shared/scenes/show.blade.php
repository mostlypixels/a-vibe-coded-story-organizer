<x-public-layout>
    <div class="max-w-3xl mx-auto px-4 py-10 space-y-6">
        {{-- Formatted title: "Chapter 1 — Chapter title: Scene title".
             Arabic chapter.position + em-dash, matching the Story overview. --}}
        <h1 class="text-3xl font-bold text-gray-900">
            {{ __('Chapter :number', ['number' => $scene->chapter->position]) }}
            &mdash; {{ $scene->chapter->name }}: {{ $scene->name }}
        </h1>

        {{-- Description in a COLLAPSED card (starts closed, per spec). The body
             is already-sanitized rich HTML, rendered only via x-rich-text. --}}
        @if (filled($scene->description))
            <div x-data="{ open: false }" class="bg-white shadow-sm rounded-lg">
                <button type="button" @click="open = ! open"
                        class="w-full flex items-center justify-between px-6 py-4 text-left">
                    <span class="font-semibold text-gray-800">{{ __('Description') }}</span>
                    <svg class="h-4 w-4 fill-current text-gray-500 transition-transform" :class="{ 'rotate-180': open }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="open" x-transition class="px-6 pb-4">
                    <x-rich-text :html="$scene->description" />
                </div>
            </div>
        @endif

        {{-- Contents rendered as formatted HTML (Markdown → HTML), the same
             render path as the Story overview. `notes` is NEVER rendered here. --}}
        <article class="prose prose-sm max-w-none text-gray-700 text-justify [&_p]:my-4">
            {!! Str::markdown($scene->contents ?? '') !!}
        </article>
    </div>
</x-public-layout>
