{{--
    The palette button in the top bar and its panel. The panel loads on the first open.
    Used once, at every width: the bar shows it beside the account menu or the hamburger.
--}}
<x-dropdown align="right" width="w-72" offset-classes="mt-0" :close-on-click="false">
    <x-slot name="trigger">
        <x-disclosure-button
            aria-label="{{ __('Appearance') }}"
            class="inline-flex h-12 items-center px-3 text-nav-content hover:bg-nav-raised/80 focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-focus transition ease-in-out duration-150">
            <x-tabler-palette class="h-6 w-6" aria-hidden="true" />
        </x-disclosure-button>
    </x-slot>

    <x-slot name="content">
        <x-navigation.appearance-switcher-panel />
    </x-slot>
</x-dropdown>
