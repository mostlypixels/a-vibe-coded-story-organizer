<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-robots-meta />

        <title>{{ $pageTitle->withPage($title ?? null) }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <x-theme-style />
    </head>
    {{-- The default text color prevents unreadable browser-black text in dark themes. --}}
    <body class="font-sans text-content antialiased">
        <x-skip-link />
        {{-- A full-height page caps the open phone menu, so the menu cannot push the page out of the window. --}}
        <div @class([
            'bg-surface',
            'min-h-screen' => ! $fullHeight,
            'flex h-dvh flex-col overflow-hidden [&_#mobile-navigation]:max-h-[60dvh] [&_#mobile-navigation]:overflow-y-auto' => $fullHeight,
        ])>
            @include('layouts.navigation')

            @if (! $breadcrumbs->isEmpty())
                <header class="bg-nav-raised shadow-sm text-nav-content [&_a]:text-nav-content [&_a:hover]:text-nav-content">
                    <div class="py-3 px-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <x-breadcrumbs :items="$breadcrumbs" />
                        </div>
                        <div class="shrink-0">{{ $headerActions ?? '' }}</div>
                    </div>
                </header>
            @elseif (isset($header))
                <header class="bg-nav-raised shadow-sm [&_h2]:text-sm [&_h2]:leading-5 [&_h2]:text-nav-content [&_a]:text-nav-content [&_a:hover]:text-nav-content">
                    <div class="py-3 px-4">
                        {{ $header }}
                    </div>
                </header>
            @endif

            @if ($fullHeight)
                <main id="main-content" tabindex="-1" class="flex min-h-0 flex-1 flex-col focus:outline-hidden">
                    <div class="shrink-0 px-4 [&>*]:mt-2 [&>*]:mb-0">
                        @include('layouts.partials.flash')
                    </div>

                    <div class="min-h-0 flex-1">
                        {{ $slot }}
                    </div>
                </main>
            @else
                <main id="main-content" tabindex="-1" class="focus:outline-hidden">
                    <div class="py-12">
                        <div class="md:max-w-(--page-width) mx-auto px-4 sm:px-6 lg:px-8">
                            @include('layouts.partials.flash')

                            {{ $slot }}
                        </div>
                    </div>
                </main>
            @endif
        </div>

        <x-autosave-status-badge />

        {{-- One delete dialog for the whole page; every delete button fills it. --}}
        <x-confirm-delete-dialog name="confirm-delete" />

        {{-- Keep x-data on this wrapper because x-dialog does not forward attributes to x-modal. --}}
        <div x-data="navigationGuard()">
            <x-dialog name="unsaved-changes-guard" :title="__('Unsaved changes')">
                {{ __('You have unsaved changes. If you leave now, they may be lost.') }}
                <x-slot name="footer">
                    <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                    <x-button variant="danger" x-on:click="confirmLeave()">{{ __('Leave anyway') }}</x-button>
                </x-slot>
            </x-dialog>
        </div>
    </body>
</html>
