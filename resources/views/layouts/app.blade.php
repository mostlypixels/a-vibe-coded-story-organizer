<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-robots-meta />

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-ocean-800 shadow [&_h2]:text-white [&_a]:text-aqua-200 [&_a:hover]:text-white">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                <div class="py-12">
                    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>

        <x-autosave-status-badge />

        {{--
            x-data lives on this wrapping div, not directly on <x-dialog> — the
            component's own root markup (dialog.blade.php -> modal.blade.php) does not
            forward extra attributes onto its inner <x-modal>, so an x-data placed
            straight on <x-dialog> is silently dropped. Alpine resolves properties/
            methods through the parent scope chain, so confirmLeave() below still
            resolves fine from inside the nested x-modal/x-dialog scopes.
        --}}
        <div x-data="navigationGuard()">
            <x-dialog name="unsaved-changes-guard" :title="__('Unsaved changes')">
                {{ __('You have unsaved changes. If you leave now, they may be lost.') }}
                <x-slot name="footer">
                    <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                    <x-button variant="danger" x-on:click="confirmLeave()">{{ __('Leave anyway') }}</x-button>
                </x-slot>
            </x-dialog>
        </div>

        <x-autosave-draft-recovery-modal />
    </body>
</html>
