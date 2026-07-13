<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    {{-- Final v1 content: a heading and a muted placeholder paragraph. No form —
         no later task enriches this page. --}}
    <x-card>
        <x-slot name="header">
            <x-heading level="3">{{ __('Appearance & accessibility') }}</x-heading>
        </x-slot>

        <p class="text-sm text-gray-600">
            {{ __('Graphical and accessibility options will live here.') }}
        </p>
    </x-card>
</x-admin-layout>
