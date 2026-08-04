@props(['title' => null, 'open' => true])

{{--
    Same container/header/body/footer structure as x-card, but the whole card is a native
    <details>/<summary> so the user can collapse it. The header's bottom border only shows
    while expanded (group-open:), so a collapsed card doesn't show a border floating under
    its title with nothing below it.
--}}
<details @if ($open) open @endif {{ $attributes->merge(['class' => 'group bg-surface-raised overflow-hidden shadow-xs sm:rounded-lg']) }}>
    {{-- list-none suppresses the native marker triangle: it sits in its own marker box, which
         pushes onto a line above whenever the summary content is a block element (e.g. the
         header slot's flex row), so the arrow renders detached above the title instead of
         beside it. The SVG below replaces it as an ordinary inline-flex sibling instead. --}}
    <summary class="flex items-center gap-2 list-none cursor-pointer select-none px-6 py-4 group-open:border-b group-open:border-border [&::-webkit-details-marker]:hidden">
        <svg class="h-4 w-4 shrink-0 fill-current text-content-muted transition-transform group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>

        <div class="min-w-0 flex-1">
            @isset($header)
                {{ $header }}
            @else
                <x-heading level="3" class="inline">{{ $title }}</x-heading>
            @endisset
        </div>
    </summary>

    <div class="px-6 py-4">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-border bg-surface-sunken px-6 py-4">
            {{ $footer }}
        </div>
    @endisset
</details>
