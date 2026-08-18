<x-app-layout>
    @isset($header)
        <x-slot name="header">
            {{ $header }}
        </x-slot>
    @endisset

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-3">
            @include('admin.partials.sidebar')
        </div>

        <div class="lg:col-span-9 space-y-6">
            {{ $slot }}
        </div>
    </div>
</x-app-layout>
