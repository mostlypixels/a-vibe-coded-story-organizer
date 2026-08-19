<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <x-robots-meta :force="true" />

        <title>{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <x-theme-style />
    </head>
    <body class="font-sans text-content antialiased">
        <div class="min-h-screen bg-surface">
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
