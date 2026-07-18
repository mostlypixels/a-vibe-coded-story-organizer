<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    @include('admin.data.partials.subnav')

    {{--
        Flash feedback for the config save (task 04) and the section-order
        move buttons, which all redirect back here.
    --}}
    @if (session('status'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 4000)"
            role="status"
            class="rounded-md border border-aqua-200 bg-aqua-50 px-4 py-3 text-sm text-navy-900 mb-6"
        >
            {{ session('status') === 'publication-settings-updated' ? __('Ebook configuration saved.') : session('status') }}
        </div>
    @endif

    <x-card>
        <x-slot name="header">
            <x-heading level="3">{{ __('Export ebook') }}</x-heading>
        </x-slot>

        <p class="text-sm text-gray-600">
            {{ __('Configure and download one of your projects as an EPUB e-book, ready to open in any e-reader.') }}
        </p>

        @if ($projects->isEmpty())
            <p class="mt-4 text-sm text-gray-600">
                {{ __('Create a project first to export it.') }}
                <a href="{{ route('projects.create') }}" class="text-ocean-600 underline hover:text-ocean-800">
                    {{ __('Create a project') }}
                </a>
            </p>
        @else
            {{--
                Project picker: a plain GET reload (no JavaScript) — matches
                the "ordinary navigation" posture of the sub-nav above. Loads
                the selected project's saved settings, or an unsaved default
                when it has never visited this form.
            --}}
            <form method="GET" action="{{ route('admin.data.export-ebook') }}" class="mt-6 max-w-lg flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label for="epub_project_id" :value="__('Project')" />
                    <select
                        id="epub_project_id"
                        name="project"
                        class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
                    >
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected($selectedProject?->id === $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <x-button variant="secondary">{{ __('Load') }}</x-button>
            </form>

            @if ($selectedProject)
                @php $sectionOrder = $setting->section_order ?? \App\Models\PublicationSetting::SECTION_KEYS; @endphp

                {{--
                    The config form (task 04): persists PublicationSetting.
                    Exporter behaviour is unchanged until task 08+ — this only
                    writes the settings. section_order round-trips via hidden
                    inputs; it is reordered separately below via its own
                    move-up/move-down actions (mirrors ActController::moveUp),
                    not by editing this form.
                --}}
                <form method="POST" action="{{ route('admin.data.publication-settings.update', $selectedProject) }}" class="mt-8 space-y-8 max-w-2xl">
                    @csrf
                    @method('patch')

                    @foreach ($sectionOrder as $sectionKey)
                        <input type="hidden" name="section_order[]" value="{{ $sectionKey }}">
                    @endforeach

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-navy-900">{{ __('Content options') }}</legend>

                        @foreach ([
                            'include_project_cover' => __('Include project cover'),
                            'include_chapter_covers' => __('Include chapter cover pages'),
                            'include_scene_titles' => __('Include scene titles'),
                            'include_act_descriptions' => __('Include act descriptions'),
                            'include_chapter_descriptions' => __('Include chapter descriptions'),
                            'include_scene_descriptions' => __('Include scene descriptions'),
                        ] as $field => $label)
                            <div>
                                <label for="{{ $field }}" class="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        id="{{ $field }}"
                                        name="{{ $field }}"
                                        value="1"
                                        @checked(old($field, $setting->{$field}))
                                        class="mt-1 rounded border-gray-300 text-ocean-600 shadow-sm focus:ring-ocean-500"
                                    >
                                    <span class="text-sm text-gray-700">{{ $label }}</span>
                                </label>
                                <x-input-error :messages="$errors->get($field)" class="mt-1" />
                            </div>
                        @endforeach
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-navy-900">{{ __('Front & back matter') }}</legend>

                        <p class="text-sm text-gray-600">
                            {{ __('The dedication, acknowledgements, preface, and postface text itself is edited on the') }}
                            <a href="{{ route('projects.edit', $selectedProject) }}" class="text-ocean-600 underline hover:text-ocean-800">
                                {{ __('project edit page') }}
                            </a>. {{ __("Toggle which of the ones you've written are included below.") }}
                        </p>

                        @foreach ([
                            'include_dedication' => ['label' => __('Include dedication'), 'value' => $selectedProject->dedication],
                            'include_acknowledgements' => ['label' => __('Include acknowledgements'), 'value' => $selectedProject->acknowledgements],
                            'include_preface' => ['label' => __('Include preface'), 'value' => $selectedProject->preface],
                            'include_postface' => ['label' => __('Include postface'), 'value' => $selectedProject->postface],
                        ] as $field => $info)
                            <div>
                                <label for="{{ $field }}" class="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        id="{{ $field }}"
                                        name="{{ $field }}"
                                        value="1"
                                        @checked(old($field, $setting->{$field}))
                                        class="mt-1 rounded border-gray-300 text-ocean-600 shadow-sm focus:ring-ocean-500"
                                    >
                                    <span class="text-sm text-gray-700">
                                        {{ $info['label'] }}
                                        <span class="text-gray-400">({{ filled($info['value']) ? __('text set') : __('empty') }})</span>
                                    </span>
                                </label>
                                <x-input-error :messages="$errors->get($field)" class="mt-1" />
                            </div>
                        @endforeach
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-navy-900">{{ __('Metadata') }}</legend>

                        @foreach ([
                            'include_author' => ['label' => __('Include author'), 'value' => $selectedProject->author],
                            'include_publisher' => ['label' => __('Include publisher'), 'value' => $selectedProject->publisher],
                            'include_rights' => ['label' => __('Include rights'), 'value' => $selectedProject->rights],
                            'include_isbn' => ['label' => __('Include ISBN'), 'value' => $selectedProject->isbn],
                        ] as $field => $info)
                            <div>
                                <label for="{{ $field }}" class="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        id="{{ $field }}"
                                        name="{{ $field }}"
                                        value="1"
                                        @checked(old($field, $setting->{$field}))
                                        class="mt-1 rounded border-gray-300 text-ocean-600 shadow-sm focus:ring-ocean-500"
                                    >
                                    <span class="text-sm text-gray-700">
                                        {{ $info['label'] }}: <em>{{ $info['value'] ?: __('not set') }}</em>
                                    </span>
                                </label>
                                <x-input-error :messages="$errors->get($field)" class="mt-1" />
                            </div>
                        @endforeach

                        <p class="text-xs text-gray-500">
                            {{ __('Change the underlying values on the') }}
                            <a href="{{ route('projects.edit', $selectedProject) }}" class="text-ocean-600 underline hover:text-ocean-800">
                                {{ __('project edit page') }}
                            </a>.
                        </p>
                    </fieldset>

                    <fieldset class="space-y-4">
                        <legend class="text-sm font-semibold text-navy-900">{{ __('Formatting') }}</legend>

                        <div>
                            <x-input-label for="chapter_title_format" :value="__('Chapter title format')" />
                            <select
                                id="chapter_title_format"
                                name="chapter_title_format"
                                class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
                            >
                                @foreach (\App\Enums\ChapterTitleFormat::cases() as $format)
                                    <option value="{{ $format->value }}" @selected(old('chapter_title_format', $setting->chapter_title_format->value) === $format->value)>
                                        {{ $format->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('chapter_title_format')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="table_of_contents_depth" :value="__('Table of contents depth')" />
                            <select
                                id="table_of_contents_depth"
                                name="table_of_contents_depth"
                                class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
                            >
                                @foreach (\App\Enums\TableOfContentsDepth::cases() as $depth)
                                    <option value="{{ $depth->value }}" @selected(old('table_of_contents_depth', $setting->table_of_contents_depth->value) === $depth->value)>
                                        {{ $depth->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('table_of_contents_depth')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="divider_type" :value="__('Divider style')" />
                            <select
                                id="divider_type"
                                name="divider_type"
                                class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
                            >
                                @foreach (\App\Enums\DividerType::cases() as $divider)
                                    <option value="{{ $divider->value }}" @selected(old('divider_type', $setting->divider_type->value) === $divider->value)>
                                        {{ $divider->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('divider_type')" class="mt-2" />
                        </div>
                    </fieldset>

                    <fieldset x-data="{ appendixOpen: {{ $setting->include_codex_appendix ? 'true' : 'false' }} }" class="space-y-3">
                        <legend class="text-sm font-semibold text-navy-900">{{ __('Appendix') }}</legend>

                        <label for="include_codex_appendix" class="flex items-start gap-3">
                            <input
                                type="checkbox"
                                id="include_codex_appendix"
                                name="include_codex_appendix"
                                value="1"
                                x-model="appendixOpen"
                                @checked(old('include_codex_appendix', $setting->include_codex_appendix))
                                class="mt-1 rounded border-gray-300 text-ocean-600 shadow-sm focus:ring-ocean-500"
                            >
                            <span class="text-sm text-gray-700">{{ __('Include codex appendix') }}</span>
                        </label>
                        <x-input-error :messages="$errors->get('include_codex_appendix')" class="mt-1" />

                        <div x-show="appendixOpen" class="ms-7 space-y-3">
                            <p class="text-sm font-medium text-gray-700">{{ __('Which entry types') }}</p>

                            @php $checkedTypes = old('appendix_entry_types', $setting->appendix_entry_types ?? []); @endphp
                            @foreach (\App\Enums\CodexEntryType::cases() as $type)
                                <label for="appendix_entry_type_{{ $type->value }}" class="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        id="appendix_entry_type_{{ $type->value }}"
                                        name="appendix_entry_types[]"
                                        value="{{ $type->value }}"
                                        @checked(in_array($type->value, $checkedTypes, true))
                                        class="rounded border-gray-300 text-ocean-600 shadow-sm focus:ring-ocean-500"
                                    >
                                    <span class="text-sm text-gray-700">{{ $type->pluralLabel() }}</span>
                                </label>
                            @endforeach
                            <x-input-error :messages="$errors->get('appendix_entry_types')" class="mt-1" />
                            <x-input-error :messages="$errors->get('appendix_entry_types.*')" class="mt-1" />

                            <label for="appendix_include_images" class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    id="appendix_include_images"
                                    name="appendix_include_images"
                                    value="1"
                                    @checked(old('appendix_include_images', $setting->appendix_include_images))
                                    class="mt-1 rounded border-gray-300 text-ocean-600 shadow-sm focus:ring-ocean-500"
                                >
                                <span class="text-sm text-gray-700">{{ __('Include images') }}</span>
                            </label>
                        </div>
                    </fieldset>

                    <x-button variant="primary">{{ __('Save configuration') }}</x-button>
                </form>

                {{--
                    Section order (overview decision #4): the fixed reading
                    order the enabled sections render in. `title` is pinned
                    first; every other entry moves independently of its
                    include-toggle above. Each move is its own PATCH, exactly
                    like Act/Chapter/Scene reordering — not part of the
                    "Save configuration" form.
                --}}
                <div class="mt-8 max-w-2xl">
                    <x-heading level="4">{{ __('Section order') }}</x-heading>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('The order enabled sections render in. A section only renders when it is both enabled above and has content.') }}
                    </p>

                    <ul class="mt-3 divide-y divide-gray-200 border border-gray-200 rounded-md">
                        @foreach ($sectionOrder as $sectionKey)
                            <li class="flex items-center justify-between px-4 py-2">
                                <span class="text-sm text-gray-700">{{ ucfirst(str_replace('_', ' ', $sectionKey)) }}</span>
                                <div class="flex gap-1">
                                    <x-icon-move-up-button
                                        :action="route('admin.data.publication-settings.section-order.move-up', ['project' => $selectedProject, 'section' => $sectionKey])"
                                        :disabled="$sectionKey === \App\Models\PublicationSetting::PINNED_FIRST_SECTION || $loop->index <= 1"
                                    />
                                    <x-icon-move-down-button
                                        :action="route('admin.data.publication-settings.section-order.move-down', ['project' => $selectedProject, 'section' => $sectionKey])"
                                        :disabled="$sectionKey === \App\Models\PublicationSetting::PINNED_FIRST_SECTION || $loop->last"
                                    />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{--
                    Download exports using the SAVED settings above — a
                    separate form/button (the grilled v1 decision), not part
                    of the config form's own submit.
                --}}
                <form method="POST" action="{{ route('admin.data.export.epub') }}" class="mt-8 max-w-lg">
                    @csrf
                    <input type="hidden" name="project_id" value="{{ $selectedProject->id }}">
                    <x-button variant="primary">{{ __('Download EPUB') }}</x-button>
                    <p class="mt-2 text-xs text-gray-500">{{ __('Exports using the saved configuration above.') }}</p>
                </form>

                <p class="mt-4 text-xs text-gray-500">
                    {{ __('For full EPUB conformance verification, validate the downloaded file with the official') }}
                    <a href="https://www.w3.org/publishing/epubcheck/" class="text-ocean-600 underline hover:text-ocean-800 focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-sm" target="_blank" rel="noopener">
                        {{ __('epubcheck') }}
                    </a>
                    {{ __('tool.') }}
                </p>
            @endif
        @endif
    </x-card>
</x-admin-layout>
