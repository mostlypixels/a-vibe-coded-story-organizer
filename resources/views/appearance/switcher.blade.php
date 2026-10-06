@php
    $fontSections = [
        'ui_font' => [
            'heading' => __('Interface font'),
            'selected' => $fonts->uiSlug,
            'rows' => [
                'ui_scale' => [__('Smaller interface text'), __('Larger interface text')],
                'ui_leading' => [__('Tighter interface spacing'), __('Roomier interface spacing')],
            ],
        ],
        'manuscript_font' => [
            'heading' => __('Manuscript font'),
            'selected' => $fonts->manuscriptSlug,
            'rows' => [
                'manuscript_scale' => [__('Smaller manuscript text'), __('Larger manuscript text')],
                'manuscript_leading' => [__('Tighter manuscript spacing'), __('Roomier manuscript spacing')],
            ],
        ],
    ];
    $rowTitles = [
        'ui_scale' => __('Size'),
        'ui_leading' => __('Spacing'),
        'manuscript_scale' => __('Size'),
        'manuscript_leading' => __('Spacing'),
    ];
@endphp

<div class="space-y-4 p-4" x-data="appearanceSwitcher({{ Js::from($config) }})">
    <fieldset>
        <legend class="mb-2 text-sm font-medium text-content">{{ __('Theme') }}</legend>

        <div class="flex flex-wrap gap-2">
            @foreach ($themes as $slug => $preset)
                <x-theme-swatch
                    :slug="$slug"
                    :preset="$preset"
                    :checked="$config['active']['theme_slug'] === $slug"
                    x-on:change="change('theme_slug', $event.target.value)"
                />
            @endforeach
        </div>
    </fieldset>

    @foreach ($fontSections as $field => $section)
        <div class="space-y-2">
            <label for="switcher-{{ str_replace('_', '-', $field) }}" class="block text-sm font-medium text-content">
                {{ $section['heading'] }}
            </label>

            <select
                id="switcher-{{ str_replace('_', '-', $field) }}"
                name="{{ $field }}"
                x-on:change="change('{{ $field }}', $event.target.value)"
                class="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-content"
            >
                @foreach ($families as $slug => $family)
                    <option
                        value="{{ $slug }}"
                        style="font-family: {{ $family['stack'] }};"
                        @selected($section['selected'] === $slug)
                    >{{ $family['name'] }}</option>
                @endforeach
            </select>

            @foreach ($section['rows'] as $rowField => [$lessLabel, $moreLabel])
                <div class="flex items-center gap-2 text-sm">
                    <span class="w-16 text-content-muted">{{ $rowTitles[$rowField] }}</span>

                    <button
                        type="button"
                        aria-label="{{ $lessLabel }}"
                        x-on:click="step('{{ $rowField }}', -1)"
                        x-bind:disabled="!canStep('{{ $rowField }}', -1)"
                        class="rounded-md border border-border p-1 text-content hover:bg-neutral disabled:opacity-40"
                    ><x-tabler-minus class="h-4 w-4" aria-hidden="true" /></button>

                    <span
                        class="min-w-12 text-center text-content"
                        aria-live="polite"
                        x-text="labels.{{ $rowField }}[active.{{ $rowField }}]"
                    >{{ $config['labels'][$rowField][$config['active'][$rowField]] }}</span>

                    <button
                        type="button"
                        aria-label="{{ $moreLabel }}"
                        x-on:click="step('{{ $rowField }}', 1)"
                        x-bind:disabled="!canStep('{{ $rowField }}', 1)"
                        class="rounded-md border border-border p-1 text-content hover:bg-neutral disabled:opacity-40"
                    ><x-tabler-plus class="h-4 w-4" aria-hidden="true" /></button>
                </div>
            @endforeach
        </div>
    @endforeach

    <p
        role="alert"
        class="text-sm text-danger"
        x-show="error"
        x-text="error"
        style="display: none;"
    ></p>

    <x-dropdown-link :href="route('admin.appearance.edit')" class="-mx-4 -mb-4 w-auto border-t border-border">
        {{ __('More settings') }}
    </x-dropdown-link>
</div>
