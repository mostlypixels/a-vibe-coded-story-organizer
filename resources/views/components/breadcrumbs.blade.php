@props(['items'])

{{--
    The project breadcrumb trail — W3C breadcrumb pattern: a labelled <nav>
    landmark wrapping an <ol>, current page marked aria-current="page",
    separators aria-hidden so a screen reader hears only the labels.

    `items` is a Breadcrumbs (or any list of Crumb — see app/Support/Crumb.php).
    Ancestor crumbs render as links (colour inherited from the header band's
    own [&_a]:text-nav-content, see layouts/app.blade.php); the current crumb
    is plain, muted text.

    Horizontal scroll rather than wrap: on a narrow viewport a long trail
    should stay one line under the nav, not push the page content down.
--}}
<nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes->merge(['class' => 'min-w-0 overflow-x-auto']) }}>
    <ol class="flex items-center gap-1 whitespace-nowrap text-sm">
        @foreach ($items as $crumb)
            <li class="flex items-center gap-1 min-w-0">
                @if ($crumb->url)
                    {{-- aria-current sits on both branches, not only the plain
                         one. Crumb permits `url` and `current` together, so the
                         marker must not depend on which branch renders. --}}
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
