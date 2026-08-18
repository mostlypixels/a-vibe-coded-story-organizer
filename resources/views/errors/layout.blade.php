<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-robots-meta :force="true" />

        <title>@yield('code') &mdash; {{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <x-theme-style />
    </head>
    <body class="font-sans text-content antialiased">
        <div class="min-h-screen bg-surface">
            @include('layouts.error-navigation')

            <main>
                <div class="max-w-3xl mx-auto px-4 py-20 text-center space-y-4">
                    <x-application-logo class="mx-auto w-16 h-16 fill-current text-content-subtle" />

                    <p class="text-sm font-semibold uppercase tracking-widest text-content-subtle">
                        @yield('code')
                    </p>

                    <x-heading level="1">
                        @yield('title')
                    </x-heading>

                    <p class="text-content-muted">
                        @yield('message')
                    </p>

                    <div class="pt-4">
                        <x-button variant="secondary" :href="auth()->check() ? route('dashboard') : url('/')">
                            {{ auth()->check() ? __('Back to dashboard') : __('Back to home') }}
                        </x-button>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
