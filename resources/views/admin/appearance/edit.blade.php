<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configuration') }}
        </h2>
    </x-slot>

    {{-- Final v1 content: a heading and a muted placeholder paragraph. No form —
         no later task enriches this page. --}}
    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Appearance & accessibility') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Graphical and accessibility options will live here.') }}
        </p>
    </div>
</x-admin-layout>
