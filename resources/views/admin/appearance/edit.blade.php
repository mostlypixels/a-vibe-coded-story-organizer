<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    @php
        $selectedTheme = old('theme_slug', $active);
        $selectedUiFont = old('ui_font', $fonts->uiSlug);
        $selectedManuscriptFont = old('manuscript_font', $fonts->manuscriptSlug);
        $selectedUiScale = old('ui_scale', $fonts->uiScaleSlug);
        $selectedManuscriptScale = old('manuscript_scale', $fonts->manuscriptScaleSlug);
        $selectedLeading = old('manuscript_leading', $fonts->leadingSlug);

        // The live preview resolves a picked slug through this map and writes
        // nothing when the slug is absent. Authored config values only: the
        // browser never assembles a CSS value out of the form.
        $stacks = array_map(fn (array $family) => $family['stack'], $families);

        $previewMap = [
            'ui_font' => $stacks,
            'manuscript_font' => $stacks,
            'ui_scale' => $uiScales,
            'manuscript_scale' => $manuscriptScales,
            'manuscript_leading' => $leadings,
        ];
    @endphp

    <form
        method="post"
        action="{{ route('admin.appearance.update') }}"
        class="space-y-6"
        x-data="fontPreview({{ Js::from($previewMap) }})"
    >
        @csrf
        @method('patch')

        <x-card class="max-w-xl">
            <x-slot name="header">
                <x-heading level="3">{{ __('Appearance & accessibility') }}</x-heading>
                <p class="mt-1 text-sm text-content-muted">
                    {{ __('Choose the colour theme this account uses across the app.') }}
                </p>
            </x-slot>

            {{-- Native radios, not a <select> and not styled <div>s: arrow-key
                 navigation comes free. The theme applies on submit; only the
                 font fields below have a live preview. --}}
            <fieldset class="space-y-3">
                <legend class="sr-only">{{ __('Theme') }}</legend>

                @php
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
                            @checked($selectedTheme === $slug)
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
        </x-card>

        <x-card class="max-w-xl mt-6">
            <x-slot name="header">
                <x-heading level="3">{{ __('Interface font') }}</x-heading>
                <p class="mt-1 text-sm text-content-muted">
                    {{ __('The typeface used for menus, buttons and labels.') }}
                </p>
            </x-slot>

            {{-- Each label renders in its own family, from authored config
                 (never input), so the list previews itself. --}}
            <fieldset class="space-y-3">
                <legend class="sr-only">{{ __('Interface font') }}</legend>

                @foreach ($families as $slug => $family)
                    <label
                        for="ui-font-{{ $slug }}"
                        class="flex items-start gap-3 rounded-md border border-border p-4 cursor-pointer"
                    >
                        <input
                            type="radio"
                            id="ui-font-{{ $slug }}"
                            name="ui_font"
                            value="{{ $slug }}"
                            class="mt-1 border-border-strong text-link focus:ring-focus"
                            @checked($selectedUiFont === $slug)
                        >

                        <span class="flex-1">
                            <span class="block text-sm font-medium text-content" style="font-family: {{ $family['stack'] }};">
                                {{ $family['name'] }}
                            </span>
                            <span class="block text-sm text-content-muted">
                                {{ $family['note'] }}
                            </span>
                        </span>
                    </label>
                @endforeach

                <x-input-error class="mt-2" :messages="$errors->get('ui_font')" />
            </fieldset>
        </x-card>

        <x-card class="max-w-xl mt-6">
            <x-slot name="header">
                <x-heading level="3">{{ __('Manuscript font') }}</x-heading>
                <p class="mt-1 text-sm text-content-muted">
                    {{ __('The typeface used for scene text and other long-form prose.') }}
                </p>
            </x-slot>

            <fieldset class="space-y-3">
                <legend class="sr-only">{{ __('Manuscript font') }}</legend>

                @foreach ($families as $slug => $family)
                    <label
                        for="manuscript-font-{{ $slug }}"
                        class="flex items-start gap-3 rounded-md border border-border p-4 cursor-pointer"
                    >
                        <input
                            type="radio"
                            id="manuscript-font-{{ $slug }}"
                            name="manuscript_font"
                            value="{{ $slug }}"
                            class="mt-1 border-border-strong text-link focus:ring-focus"
                            @checked($selectedManuscriptFont === $slug)
                        >

                        <span class="flex-1">
                            <span class="block text-sm font-medium text-content" style="font-family: {{ $family['stack'] }};">
                                {{ $family['name'] }}
                            </span>
                            <span class="block text-sm text-content-muted">
                                {{ $family['note'] }}
                            </span>
                        </span>
                    </label>
                @endforeach

                <x-input-error class="mt-2" :messages="$errors->get('manuscript_font')" />
            </fieldset>

            {{-- Real text, not aria-hidden: a screen-reader user changing line
                 spacing for a sighted partner still needs to read the sample.

                 The three manuscript variables, not the resolved values: the
                 page-wide <style> block gives them the saved values on load,
                 and the live preview repaints the sample with the rest of the
                 page. --}}
            <div
                class="mt-4 rounded-md border border-border p-4"
                style="font-family: var(--font-manuscript); font-size: var(--manuscript-scale); line-height: var(--manuscript-leading);"
            >
                {{ __('The lighthouse keeper climbed the spiral stair by lamplight, counting each worn stone step the way she had every night for eleven years, and still the sea below sounded like something new.') }}
            </div>
        </x-card>

        <x-card class="max-w-xl mt-6">
            <x-slot name="header">
                <x-heading level="3">{{ __('Text size') }}</x-heading>
                <p class="mt-1 text-sm text-content-muted">
                    {{ __('Manuscript size is relative to the interface size, and the two compose.') }}
                </p>
            </x-slot>

            <div class="space-y-6">
                <fieldset class="space-y-3">
                    <legend class="text-sm font-medium text-content">{{ __('Interface size') }}</legend>

                    @foreach ($uiScales as $slug => $value)
                        <label
                            for="ui-scale-{{ $slug }}"
                            class="flex items-center gap-3 rounded-md border border-border p-4 cursor-pointer"
                        >
                            <input
                                type="radio"
                                id="ui-scale-{{ $slug }}"
                                name="ui_scale"
                                value="{{ $slug }}"
                                class="border-border-strong text-link focus:ring-focus"
                                @checked($selectedUiScale === $slug)
                            >
                            <span class="text-sm text-content">{{ __(ucfirst($slug)) }}</span>
                        </label>
                    @endforeach

                    <x-input-error class="mt-2" :messages="$errors->get('ui_scale')" />
                </fieldset>

                <fieldset class="space-y-3">
                    <legend class="text-sm font-medium text-content">
                        {{ __('Manuscript size') }}
                        <span class="block text-sm font-normal text-content-muted">
                            {{ __('Relative to the interface size above.') }}
                        </span>
                    </legend>

                    @foreach ($manuscriptScales as $slug => $value)
                        <label
                            for="manuscript-scale-{{ $slug }}"
                            class="flex items-center gap-3 rounded-md border border-border p-4 cursor-pointer"
                        >
                            <input
                                type="radio"
                                id="manuscript-scale-{{ $slug }}"
                                name="manuscript_scale"
                                value="{{ $slug }}"
                                class="border-border-strong text-link focus:ring-focus"
                                @checked($selectedManuscriptScale === $slug)
                            >
                            <span class="text-sm text-content">{{ __(ucfirst($slug)) }}</span>
                        </label>
                    @endforeach

                    <x-input-error class="mt-2" :messages="$errors->get('manuscript_scale')" />
                </fieldset>
            </div>
        </x-card>

        <x-card class="max-w-xl mt-6">
            <x-slot name="header">
                <x-heading level="3">{{ __('Line spacing') }}</x-heading>
                <p class="mt-1 text-sm text-content-muted">
                    {{ __('Space between lines of manuscript text.') }}
                </p>
            </x-slot>

            <fieldset class="space-y-3">
                <legend class="sr-only">{{ __('Line spacing') }}</legend>

                @foreach ($leadings as $slug => $value)
                    <label
                        for="leading-{{ $slug }}"
                        class="flex items-center gap-3 rounded-md border border-border p-4 cursor-pointer"
                    >
                        <input
                            type="radio"
                            id="leading-{{ $slug }}"
                            name="manuscript_leading"
                            value="{{ $slug }}"
                            class="border-border-strong text-link focus:ring-focus"
                            @checked($selectedLeading === $slug)
                        >
                        <span class="text-sm text-content">{{ __(ucfirst($slug)) }}</span>
                    </label>
                @endforeach

                <x-input-error class="mt-2" :messages="$errors->get('manuscript_leading')" />
            </fieldset>
        </x-card>

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
</x-admin-layout>
