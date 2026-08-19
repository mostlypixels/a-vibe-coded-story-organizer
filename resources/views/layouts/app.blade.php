<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-robots-meta />

        <title>{{ $pageTitle }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <x-theme-style />
    </head>
    {{-- The default text color prevents unreadable browser-black text in dark themes. --}}
    <body class="font-sans text-content antialiased">
        <div class="min-h-screen bg-surface">
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

            <main>
                <div class="py-12">
                    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                        @if (session('status') === 'reverted-save')
                            <x-alert variant="success" dismissible class="mb-6">
                                {{ __('Save undone.') }}
                                @if (session('restored_fields'))
                                    {{ __('Restored: :fields.', ['fields' => implode(', ', session('restored_fields'))]) }}
                                @endif
                            </x-alert>
                        @endif

                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>

        <x-autosave-status-badge />

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
