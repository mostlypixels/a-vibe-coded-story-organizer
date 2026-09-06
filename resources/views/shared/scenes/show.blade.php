<x-public-layout>
    <div class="max-w-3xl mx-auto px-4 py-10 space-y-6">
        <x-heading level="1">
            {{ __('Chapter :number', ['number' => $numbering->chapter($scene->chapter)]) }}
            &mdash; {{ $scene->chapter->name }}: {{ $scene->name }}
        </x-heading>

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

        <x-scene-prose :scene="$scene" />
    </div>
</x-public-layout>
