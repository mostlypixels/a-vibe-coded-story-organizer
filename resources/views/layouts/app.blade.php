<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-robots-meta />

        <title>{{ $pageTitle }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-ocean-700 shadow [&_h2]:text-sm [&_h2]:text-white [&_a]:text-aqua-100 [&_a:hover]:text-white">
                    {{-- Same full-bleed treatment as layouts.navigation: spans the
                         viewport with a px-2 gutter rather than the max-w-7xl box
                         <main> still uses. --}}
                    <div class="py-3 px-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                <div class="py-12">
                    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                        {{--
                            "Undo this save" lands on the entity's edit form, and
                            all seven edit forms reach this shell — six through
                            <x-edit-layout>, codex directly — so the confirmation
                            lives here rather than being repeated in each. Scoped
                            to this one status value, so no other page's flash can
                            surface through it.
                        --}}
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
