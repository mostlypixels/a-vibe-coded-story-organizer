<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Shared links are private-by-link: keep them out of search engines
             even if the URL is forwarded or posted somewhere public. Forced on
             regardless of the global crawler toggle. --}}
        <x-robots-meta :force="true" />

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts (compiled CSS gives us Tailwind Typography `prose` + app styles; Alpine for the collapse) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        {{-- No navigation and no user chrome: this page is link-only and the
             visitor is unauthenticated. A wide, comfortable reading column. --}}
        <div class="min-h-screen bg-gray-100">
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
