{{--
    The Admin Configuration shell. Wraps <x-app-layout> so every section inherits
    the top nav and the x-robots-meta head tag, and lays out a responsive
    two-column grid: the sidebar section switcher beside the section content.
    An optional `header` slot is forwarded to x-app-layout's page-heading band.
--}}
<x-app-layout>
    @isset($header)
        <x-slot name="header">
            {{ $header }}
        </x-slot>
    @endisset

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-12">
        {{-- Sidebar stacks above the content on mobile (semantic order already
             correct) and sits beside it on md+. --}}
        <div class="md:flex md:gap-8">
            <div class="md:w-64 md:shrink-0 mb-6 md:mb-0">
                @include('admin.partials.sidebar')
            </div>

            <div class="flex-1 min-w-0 space-y-6">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-app-layout>
