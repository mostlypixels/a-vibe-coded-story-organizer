<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    <x-card class="max-w-xl">
        <x-slot name="header">
            <x-heading level="3">{{ __('Appearance & accessibility') }}</x-heading>
            <p class="mt-1 text-sm text-content-muted">
                {{ __('Choose the colour theme this account uses across the app.') }}
            </p>
        </x-slot>

        <form method="post" action="{{ route('admin.appearance.update') }}" class="space-y-6">
            @csrf
            @method('patch')

            {{-- Native radios, not a <select> and not styled <div>s: arrow-key
                 navigation comes free and nothing needs Alpine. Apply on submit —
                 no live preview here, that's spec 3's job. --}}
            <fieldset class="space-y-3">
                <legend class="sr-only">{{ __('Theme') }}</legend>

                @php
                    $selectedSlug = old('theme_slug', $active);
                    // The swatch strip is decoration only (aria-hidden below); the
                    // visible text label carries the preset name. Values are data,
                    // not classes, so they're re-validated with the same pattern
                    // ThemeStyleBlock uses before ever reaching an inline style.
                    $swatchTokens = ['surface', 'surface-raised', 'content', 'primary', 'accent', 'focus'];
                @endphp

                @foreach ($themes as $slug => $preset)
                    <label
                        for="theme-{{ $slug }}"
                        class="flex items-start gap-3 rounded-md border border-border p-4 cursor-pointer"
                    >
                        <input
                            type="radio"
                            id="theme-{{ $slug }}"
                            name="theme_slug"
                            value="{{ $slug }}"
                            class="mt-1 border-border-strong text-link focus:ring-focus"
                            @checked($selectedSlug === $slug)
                        >

                        <span class="flex-1">
                            <span class="block text-sm font-medium text-content">
                                {{ __($preset->name) }}
                            </span>

                            <span class="mt-2 flex gap-1" aria-hidden="true">
                                @foreach ($swatchTokens as $token)
                                    @continue(! preg_match(\App\Support\Oklch::CSS_VALUE_PATTERN, $preset->tokens[$token] ?? ''))
                                    <span
                                        class="h-6 w-6 rounded-full border border-border"
                                        style="background-color: {{ $preset->tokens[$token] }};"
                                    ></span>
                                @endforeach
                            </span>
                        </span>
                    </label>
                @endforeach

                <x-input-error class="mt-2" :messages="$errors->get('theme_slug')" />
            </fieldset>

            <div class="flex items-center gap-4">
                <x-button variant="primary" :icon="true">{{ __('Save') }}</x-button>

                @if (session('status') === 'theme-updated')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2000)"
                        class="text-sm text-content-muted"
                    >{{ __('Saved.') }}</p>
                @endif
            </div>
        </form>
    </x-card>
</x-admin-layout>
