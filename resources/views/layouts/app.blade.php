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

        <x-theme-style />
    </head>
    {{-- `text-content` is the page's default voice, and it is load-bearing rather than
         cosmetic: without it the body falls back to the browser's black, so anything that
         forgets to name a colour is invisible under a dark preset. `layouts/guest` and
         `welcome` already set it; this and `layouts/public` were the two that did not. --}}
    <body class="font-sans text-content antialiased">
        <div class="min-h-screen bg-surface">
            @include('layouts.navigation')

            <!-- Page Heading band -->
            {{-- In-project routes get a breadcrumb trail (built centrally by the
                 view composer off `routeProject`); everything else falls back to
                 the page's own `header` slot. Never both — breadcrumbs replace both
                 the old page title and its "Back to X" link. See
                 documentation/architecture.md → Breadcrumbs. --}}
            @if (! $breadcrumbs->isEmpty())
                {{-- text-nav-content colours the plain <span> crumbs (section
                     triggers + current leaf); the [&_a] rules override the global
                     anchor colour so links match rather than paint default blue. --}}
                <header class="bg-nav-raised shadow-sm text-nav-content [&_a]:text-nav-content [&_a:hover]:text-nav-content">
                    {{-- Two-column band: breadcrumbs left, a reserved (currently
                         empty) action slot right — see $headerActions below. --}}
                    <div class="py-3 px-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <x-breadcrumbs :items="$breadcrumbs" />
                        </div>
                        {{-- $headerActions is a named slot on <x-app-layout>,
                             reserved for a future "page actions" spec. Intentionally
                             empty today — declared so the column is already here. --}}
                        <div class="shrink-0">{{ $headerActions ?? '' }}</div>
                    </div>
                </header>
            @elseif (isset($header))
                {{-- [&_h2]:leading-5 restores what Tailwind 3 did implicitly: there,
                     `text-sm` set font-size *and* line-height in one rule, and the
                     descendant selector out-specified the `leading-tight` the page
                     headings carry. In v4 `leading-*` sets a custom property on the
                     element itself, which always wins over an ancestor's, so the
                     line-height has to be stated here to keep the header band at its
                     original height. --}}
                <header class="bg-nav-raised shadow-sm [&_h2]:text-sm [&_h2]:leading-5 [&_h2]:text-nav-content [&_a]:text-nav-content [&_a:hover]:text-nav-content">
                    {{-- Same full-bleed treatment as layouts.navigation: spans the
                         viewport with a px-2 gutter rather than the max-w-7xl box
                         <main> still uses. --}}
                    <div class="py-3 px-4">
                        {{ $header }}
                    </div>
                </header>
            @endif

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
