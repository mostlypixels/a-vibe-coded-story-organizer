<nav aria-label="Configuration">
    <ul class="space-y-1">
        @foreach ($adminNavigation->sections() as $section)
            <li>
                <x-sidebar-link
                    :href="$section['href']"
                    :active="$section['active']"
                    class="block ps-3 pe-4 py-2 border-l-4 text-sm focus:bg-neutral"
                >
                    {{ $section['label'] }}
                </x-sidebar-link>
            </li>
        @endforeach
    </ul>
</nav>
