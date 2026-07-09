<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configuration') }}
        </h2>
    </x-slot>

    {{--
        Export & import shell (task 03). The backup/restore engine is a separate
        future spec (Q3): both tabs render a short "coming soon" line only — no
        forms, no routes that post anywhere.

        Tabs are inline Alpine (Q5: no reusable x-tabs component until a second
        screen needs one). The tabs follow the WAI-ARIA tabs pattern:
        role="tablist"/tab/tabpanel, aria-selected on the active tab, aria-controls
        wiring tab -> panel, a roving tabindex (only the active tab is in the tab
        order), and Left/Right arrow keys move between tabs. `activeTab` is the
        single source of truth for which tab/panel shows.
    --}}
    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg" x-data="{ activeTab: 'export' }">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Export & import') }}
        </h2>

        <div class="mt-6 border-b border-gray-200">
            <div
                role="tablist"
                aria-label="{{ __('Export and import') }}"
                class="-mb-px flex gap-2"
            >
                <button
                    id="tab-export"
                    type="button"
                    role="tab"
                    x-ref="tabExport"
                    aria-controls="panel-export"
                    :aria-selected="activeTab === 'export' ? 'true' : 'false'"
                    :tabindex="activeTab === 'export' ? 0 : -1"
                    @click="activeTab = 'export'"
                    @keydown.right.prevent="activeTab = 'import'; $refs.tabImport.focus()"
                    @keydown.left.prevent="activeTab = 'import'; $refs.tabImport.focus()"
                    :class="activeTab === 'export'
                        ? 'border-flame-500 text-navy-900'
                        : 'border-transparent text-gray-500 hover:text-navy-900 hover:border-gray-300'"
                    class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-sm transition ease-in-out duration-150"
                >
                    {{ __('Export') }}
                </button>

                <button
                    id="tab-import"
                    type="button"
                    role="tab"
                    x-ref="tabImport"
                    aria-controls="panel-import"
                    :aria-selected="activeTab === 'import' ? 'true' : 'false'"
                    :tabindex="activeTab === 'import' ? 0 : -1"
                    @click="activeTab = 'import'"
                    @keydown.right.prevent="activeTab = 'export'; $refs.tabExport.focus()"
                    @keydown.left.prevent="activeTab = 'export'; $refs.tabExport.focus()"
                    :class="activeTab === 'import'
                        ? 'border-flame-500 text-navy-900'
                        : 'border-transparent text-gray-500 hover:text-navy-900 hover:border-gray-300'"
                    class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-sm transition ease-in-out duration-150"
                >
                    {{ __('Import') }}
                </button>
            </div>
        </div>

        <div
            id="panel-export"
            role="tabpanel"
            aria-labelledby="tab-export"
            tabindex="0"
            x-show="activeTab === 'export'"
            class="mt-6 focus:outline-none"
        >
            <p class="text-sm text-gray-600">
                {{ __('Exporting your data will be available soon.') }}
            </p>
        </div>

        {{-- The inactive panel starts hidden via an inline style so there is no
             flash before Alpine boots; x-show then manages its visibility. --}}
        <div
            id="panel-import"
            role="tabpanel"
            aria-labelledby="tab-import"
            tabindex="0"
            x-show="activeTab === 'import'"
            style="display: none;"
            class="mt-6 focus:outline-none"
        >
            <p class="text-sm text-gray-600">
                {{ __('Importing a backup will be available soon.') }}
            </p>
        </div>
    </div>
</x-admin-layout>
