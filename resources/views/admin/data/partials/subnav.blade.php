@php
    // Ordinary navigation links to distinct URLs — NOT JS tabs (task 03: "no
    // longer javascript tabs, but separate controller actions"). Reuses
    // admin/partials/sidebar.blade.php's active-state idiom: aria-current="page"
    // (never colour-only) on the current page's link, driven by routeIs().
    $links = [
        ['route' => 'admin.data.export-project', 'label' => __('Export project')],
        ['route' => 'admin.data.export-ebook', 'label' => __('Export ebook')],
        ['route' => 'admin.data.import.index', 'label' => __('Import')],
    ];

    $activeClasses = 'border-flame-500 text-navy-900';
    $inactiveClasses = 'border-transparent text-gray-500 hover:text-navy-900 hover:border-gray-300';
@endphp

<nav aria-label="{{ __('Export and import') }}" class="border-b border-gray-200 mb-6">
    <ul class="-mb-px flex gap-2">
        @foreach ($links as $link)
            @php $active = request()->routeIs($link['route']); @endphp
            <li>
                <a
                    href="{{ route($link['route']) }}"
                    @if ($active) aria-current="page" @endif
                    class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-sm transition ease-in-out duration-150 {{ $active ? $activeClasses : $inactiveClasses }}"
                >
                    {{ $link['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
