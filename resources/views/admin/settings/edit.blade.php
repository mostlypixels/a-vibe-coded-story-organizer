<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    <x-card class="max-w-xl">
        <x-slot name="header">
            <x-heading level="3">{{ __('General settings') }}</x-heading>
            <p class="mt-1 text-sm text-gray-600">
                {{ __('Control whether search engines and crawlers may index this site.') }}
            </p>
        </x-slot>

        <form method="post" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf
            @method('patch')

            {{-- Hidden mode toggle. A plain semantic checkbox: there is no
                 x-checkbox component in this project. --}}
            <div class="flex items-start gap-3">
                <input
                    type="checkbox"
                    id="enabled"
                    name="enabled"
                    value="1"
                    @checked(old('enabled', $setting->enabled))
                    class="mt-1 rounded border-gray-300 text-ocean-600 shadow-sm focus:ring-ocean-500"
                >
                <div>
                    <x-input-label for="enabled" :value="__('Hide this site from search engines')" />
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('When on, the site is hidden from search engines and crawlers.') }}
                    </p>
                </div>
            </div>

            {{-- Whitelist textarea (one term per line). The value must survive
                 both a fresh load (array from the DB) and a validation redirect
                 (array from old()). --}}
            @php
                $whitelistOld = old('user_agent_whitelist');
                $whitelistText = is_array($whitelistOld)
                    ? implode("\n", $whitelistOld)
                    : ($whitelistOld ?? implode("\n", $setting->whitelistTerms()));
            @endphp

            <div>
                <x-input-label for="user_agent_whitelist" :value="__('Allowed crawlers')" />
                <textarea
                    id="user_agent_whitelist"
                    name="user_agent_whitelist"
                    rows="5"
                    class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
                >{{ $whitelistText }}</textarea>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('One user-agent term per line (e.g. Googlebot). These crawlers stay allowed while hidden mode is on.') }}
                </p>
                <x-input-error class="mt-2" :messages="$errors->get('user_agent_whitelist.*')" />
            </div>

            <div class="flex items-center gap-4">
                <x-button variant="primary" :icon="true">{{ __('Save') }}</x-button>

                <a
                    href="{{ route('robots.txt') }}"
                    target="_blank"
                    rel="noopener"
                    class="text-sm text-gray-600 underline hover:text-gray-900"
                >{{ __('Preview robots.txt') }}</a>

                @if (session('status') === 'crawler-settings-updated')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2000)"
                        class="text-sm text-gray-600"
                    >{{ __('Saved.') }}</p>
                @endif
            </div>
        </form>
    </x-card>
</x-admin-layout>
