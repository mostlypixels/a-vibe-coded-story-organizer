@php
    // One source of truth for which admin section is active — the documented nav
    // active-state convention (documentation/architecture.md -> Navigation active
    // state). Each link carries aria-current="page" when active (never
    // colour-only — tests assert on aria-current), plus the highlight classes.
    $generalActive    = request()->routeIs('admin.settings.*');
    $appearanceActive = request()->routeIs('admin.appearance.*');
    $dataActive       = request()->routeIs('admin.data.*');
    $databaseActive   = request()->routeIs('admin.database.*');

    $sections = [
        ['label' => 'General settings',          'href' => route('admin.settings.edit'),   'active' => $generalActive],
        ['label' => 'Appearance & accessibility', 'href' => route('admin.appearance.edit'), 'active' => $appearanceActive],
        ['label' => 'Export & import',            'href' => route('admin.data.index'),      'active' => $dataActive],
        ['label' => 'Database configuration',     'href' => route('admin.database.edit'),   'active' => $databaseActive],
    ];

    $activeClasses = 'border-flame-500 bg-aqua-50 text-navy-900 font-semibold';
    $inactiveClasses = 'border-transparent text-gray-700 hover:bg-gray-100 hover:text-navy-900';
@endphp

<nav aria-label="Configuration">
    <ul class="space-y-1">
        @foreach ($sections as $section)
            <li>
                <a
                    href="{{ $section['href'] }}"
                    @if ($section['active']) aria-current="page" @endif
                    class="block ps-3 pe-4 py-2 border-l-4 text-sm no-underline hover:no-underline focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out {{ $section['active'] ? $activeClasses : $inactiveClasses }}"
                >
                    {{ __($section['label']) }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
