@props(['items'])

<nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes->merge(['class' => 'min-w-0 overflow-x-auto']) }}>
    <ol class="flex items-center gap-1 whitespace-nowrap text-sm">
        @foreach ($items as $crumb)
            <li class="flex items-center gap-1 min-w-0">
                @if ($crumb->url)
                    <a href="{{ $crumb->url }}" @if ($crumb->current) aria-current="page" @endif class="hover:underline truncate max-w-[16rem]">{{ $crumb->label }}</a>
                @else
                    <span
                        @if ($crumb->current) aria-current="page" @endif
                        class="truncate max-w-[16rem] {{ $crumb->current ? 'font-medium' : 'opacity-70' }}"
                    >{{ $crumb->label }}</span>
                @endif

                @unless ($loop->last)
                    <x-tabler-chevron-right class="h-4 w-4 shrink-0 opacity-50" aria-hidden="true" />
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
