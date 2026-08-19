<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Appearance & accessibility') }}
        </x-heading>
    </x-slot>

    @php
        $selectedTheme = old('theme_slug', $active);
        $selectedUiFont = old('ui_font', $fonts->uiSlug);
        $selectedManuscriptFont = old('manuscript_font', $fonts->manuscriptSlug);
        $selectedUiScale = old('ui_scale', $fonts->uiScaleSlug);
        $selectedManuscriptScale = old('manuscript_scale', $fonts->manuscriptScaleSlug);
        $selectedLeading = old('manuscript_leading', $fonts->leadingSlug);
        $selectedUiLeading = old('ui_leading', $fonts->uiLeadingSlug);

        $stacks = array_map(fn (array $family) => $family['stack'], $families);

        // Only server-approved values can reach CSS properties.
        $previewMap = [
            'theme_slug' => $themeDeclarations,
            'ui_font' => $stacks,
            'manuscript_font' => $stacks,
            'ui_scale' => $uiScales,
            'manuscript_scale' => $manuscriptScales,
            'manuscript_leading' => $manuscriptLineHeights,
            'ui_leading' => $uiLineHeights,
        ];
    @endphp

    {{-- Disable radio restoration because it does not trigger the live preview. --}}
    <form
        method="post"
        action="{{ route('admin.appearance.update') }}"
        class="space-y-6"
        autocomplete="off"
        x-data="fontPreview({{ Js::from($previewMap) }})"
    >
        @csrf
        @method('patch')

        <x-card class="max-w-5xl">
            <x-slot name="header">
                <x-heading level="3">{{ __('Colour theme') }}</x-heading>
                <p class="mt-1 text-sm text-content-muted">
                    {{ __('Choose the colour theme this account uses across the app.') }}
                </p>
            </x-slot>

            <fieldset>
                <legend class="sr-only">{{ __('Theme') }}</legend>

                <div class="grid grid-cols-5 gap-3">
                    @foreach ($themes as $slug => $preset)
                        <x-theme-card
                            :slug="$slug"
                            :preset="$preset"
                            :checked="$selectedTheme === $slug"
                        />
                    @endforeach
                </div>

                <x-input-error class="mt-2" :messages="$errors->get('theme_slug')" />
            </fieldset>
        </x-card>

        <x-card class="max-w-5xl mt-6">
            <x-slot name="header">
                <x-heading level="3">{{ __('Interface font') }}</x-heading>
                <p class="mt-1 text-sm text-content-muted">
                    {{ __('The typeface used for menus, buttons and labels.') }}
                </p>
            </x-slot>

            <div class="space-y-6">
                <fieldset>
                    <legend class="sr-only">{{ __('Interface font') }}</legend>

                    <div class="grid grid-cols-5 gap-3">
                        @foreach ($families as $slug => $family)
                            <x-font-card
                                name="ui_font"
                                :slug="$slug"
                                :family="$family"
                                :checked="$selectedUiFont === $slug"
                            />
                        @endforeach
                    </div>

                    <x-input-error class="mt-2" :messages="$errors->get('ui_font')" />
                </fieldset>

                <div class="grid gap-6 sm:grid-cols-2">
                    <x-setting-track
                        name="ui_scale"
                        format="px"
                        :legend="__('Text size')"
                        :options="$uiScales"
                        :selected="$selectedUiScale"
                    />

                    <x-setting-track
                        name="ui_leading"
                        format="times"
                        :legend="__('Line spacing')"
                        :options="$leadings"
                        :selected="$selectedUiLeading"
                    />
                </div>

                <p class="rounded-md border border-border p-4 text-sm text-content">
                    {{ __('Menus, buttons and labels use this typeface at this size and spacing.') }}
                </p>
            </div>
        </x-card>

        <x-card class="max-w-5xl mt-6">
            <x-slot name="header">
                <x-heading level="3">{{ __('Manuscript font') }}</x-heading>
                <p class="mt-1 text-sm text-content-muted">
                    {{ __('The typeface used for scene text and other long-form prose.') }}
                </p>
            </x-slot>

            <div class="space-y-6">
                <fieldset>
                    <legend class="sr-only">{{ __('Manuscript font') }}</legend>

                    <div class="grid grid-cols-5 gap-3">
                        @foreach ($families as $slug => $family)
                            <x-font-card
                                name="manuscript_font"
                                :slug="$slug"
                                :family="$family"
                                :checked="$selectedManuscriptFont === $slug"
                            />
                        @endforeach
                    </div>

                    <x-input-error class="mt-2" :messages="$errors->get('manuscript_font')" />
                </fieldset>

                <div class="grid gap-6 sm:grid-cols-2">
                    <x-setting-track
                        name="manuscript_scale"
                        format="times"
                        :legend="__('Text size')"
                        :hint="__('Relative to the interface size above.')"
                        :options="$manuscriptScales"
                        :selected="$selectedManuscriptScale"
                    />

                    <x-setting-track
                        name="manuscript_leading"
                        format="times"
                        :legend="__('Line spacing')"
                        :hint="__('Multiplies the default manuscript spacing.')"
                        :options="$leadings"
                        :selected="$selectedLeading"
                    />
                </div>

                <div
                    class="rounded-md border border-border p-4"
                    style="font-family: var(--font-manuscript); font-size: var(--manuscript-scale); line-height: var(--manuscript-leading);"
                >
                    {{ __('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.') }}
                </div>
            </div>
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
