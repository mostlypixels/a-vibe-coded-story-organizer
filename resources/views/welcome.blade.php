<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <x-robots-meta />

        <title>{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <x-theme-style />
    </head>
    <body class="font-sans text-content antialiased bg-surface">
        <div class="min-h-screen flex flex-col items-center justify-center gap-6">
            <div class="flex items-center gap-3">
                <x-application-logo class="w-12 h-12 fill-current text-content" />
                <span class="text-2xl font-semibold">{{ config('app.name') }}</span>
            </div>

            @auth
                <x-button href="{{ route('dashboard') }}">Dashboard</x-button>
            @else
                <x-button href="{{ route('login') }}">Log in</x-button>
            @endauth
        </div>
    </body>
</html>
