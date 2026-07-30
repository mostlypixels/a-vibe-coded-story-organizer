{{-- Ordinary navigation links to distinct URLs — NOT JS tabs: each is its own
     controller action. Links and matchers come from AdminNavigation, shared with
     the Configuration sidebar. --}}
<nav aria-label="{{ __('Export and import') }}" class="border-b border-gray-200 mb-6">
    <ul class="-mb-px flex gap-2">
        @foreach ($adminNavigation->dataSubnav() as $link)
            <li>
                <x-sidebar-link
                    variant="tab"
                    :href="$link['href']"
                    :active="$link['active']"
                    class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium rounded-sm focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2"
                >
                    {{ $link['label'] }}
                </x-sidebar-link>
            </li>
        @endforeach
    </ul>
</nav>
