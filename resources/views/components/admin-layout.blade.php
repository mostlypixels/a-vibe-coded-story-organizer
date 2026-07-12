{{--
    The Admin Configuration shell. Wraps <x-app-layout> so every section inherits
    the top nav, the x-robots-meta head tag, and the shared max-w-7xl container, and
    lays out a 12-col grid: the sidebar section switcher (3 cols) beside the section
    content (9 cols), matching the app-wide 9-3 edit-form convention. An optional
    `header` slot is forwarded to x-app-layout's page-heading band.
--}}
<x-app-layout>
    @isset($header)
        <x-slot name="header">
            {{ $header }}
        </x-slot>
    @endisset

    {{-- Sidebar stacks above the content on mobile (semantic order already
         correct) and sits beside it on lg+. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-3">
            @include('admin.partials.sidebar')
        </div>

        <div class="lg:col-span-9 space-y-6">
            {{ $slot }}
        </div>
    </div>
</x-app-layout>
