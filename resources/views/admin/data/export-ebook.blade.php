<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    @include('admin.data.partials.subnav')

    {{--
        Flash feedback for the config save and the section-order
        move buttons, which all redirect back here.
    --}}
    @if (session('status'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 4000)"
            role="status"
            class="rounded-md border border-info bg-info-surface px-4 py-3 text-sm text-info-surface-content mb-6"
        >
            {{ session('status') === 'publication-settings-updated' ? __('Ebook configuration saved.') : session('status') }}
        </div>
    @endif

    <x-card>
        <x-slot name="header">
            <x-heading level="3">{{ __('Export ebook') }}</x-heading>
        </x-slot>

        <p class="text-sm text-content-muted">
            {{ __('Configure and download one of your projects as an EPUB e-book, ready to open in any e-reader.') }}
        </p>

        @if ($projects->isEmpty())
            <p class="mt-4 text-sm text-content-muted">
                {{ __('Create a project first to export it.') }}
                <a href="{{ route('projects.create') }}" class="text-link underline hover:text-link-hover">
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
                    <x-select id="epub_project_id" name="project" class="mt-1 block w-full">
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected($selectedProject?->id === $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </x-select>
                </div>
                <x-button variant="primary">{{ __('Load') }}</x-button>
            </form>

            @if ($selectedProject)
                @php $sectionOrder = $setting->section_order ?? \App\Models\PublicationSetting::SECTION_KEYS; @endphp

                <fieldset class="mt-8 border border-border rounded-md px-4 pb-4">
                    <legend class="px-2 text-sm font-semibold text-content">{{ $selectedProject->name }}</legend>

                {{--
                    The config form: persists PublicationSetting. This only
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
                        <legend class="text-sm font-semibold text-content">{{ __('Content options') }}</legend>

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
                                        class="mt-1 rounded-sm border-border-strong text-link shadow-xs focus:ring-focus"
                                    >
                                    <span class="text-sm text-content-muted">{{ $label }}</span>
                                </label>
                                <x-input-error :messages="$errors->get($field)" class="mt-1" />
                            </div>
                        @endforeach
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-content">{{ __('Front & back matter') }}</legend>

                        <p class="text-sm text-content-muted">
                            {{ __('The dedication, acknowledgements, preface, and postface text itself is edited on the') }}
                            <a href="{{ route('projects.edit', $selectedProject) }}" class="text-link underline hover:text-link-hover">
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
                                        class="mt-1 rounded-sm border-border-strong text-link shadow-xs focus:ring-focus"
                                    >
                                    <span class="text-sm text-content-muted">
                                        {{ $info['label'] }}
                                        <span class="text-content-subtle">({{ filled($info['value']) ? __('text set') : __('empty') }})</span>
                                    </span>
                                </label>
                                <x-input-error :messages="$errors->get($field)" class="mt-1" />
                            </div>
                        @endforeach
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-content">{{ __('Metadata') }}</legend>

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
                                        class="mt-1 rounded-sm border-border-strong text-link shadow-xs focus:ring-focus"
                                    >
                                    <span class="text-sm text-content-muted">
                                        {{ $info['label'] }}: <em>{{ $info['value'] ?: __('not set') }}</em>
                                    </span>
                                </label>
                                <x-input-error :messages="$errors->get($field)" class="mt-1" />
                            </div>
                        @endforeach

                        <p class="text-xs text-content-muted">
                            {{ __('Change the underlying values on the') }}
                            <a href="{{ route('projects.edit', $selectedProject) }}" class="text-link underline hover:text-link-hover">
                                {{ __('project edit page') }}
                            </a>.
                        </p>
                    </fieldset>

                    <fieldset class="space-y-4">
                        <legend class="text-sm font-semibold text-content">{{ __('Formatting') }}</legend>

                        <div>
                            <x-input-label for="chapter_title_format" :value="__('Chapter title format')" />
                            <x-select id="chapter_title_format" name="chapter_title_format" class="mt-1 block w-full">
                                @foreach (\App\Enums\ChapterTitleFormat::cases() as $format)
                                    <option value="{{ $format->value }}" @selected(old('chapter_title_format', $setting->chapter_title_format->value) === $format->value)>
                                        {{ $format->label() }}
                                    </option>
                                @endforeach
                            </x-select>
                            <x-input-error :messages="$errors->get('chapter_title_format')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="table_of_contents_depth" :value="__('Table of contents depth')" />
                            <x-select id="table_of_contents_depth" name="table_of_contents_depth" class="mt-1 block w-full">
                                @foreach (\App\Enums\TableOfContentsDepth::cases() as $depth)
                                    <option value="{{ $depth->value }}" @selected(old('table_of_contents_depth', $setting->table_of_contents_depth->value) === $depth->value)>
                                        {{ $depth->label() }}
                                    </option>
                                @endforeach
                            </x-select>
                            <x-input-error :messages="$errors->get('table_of_contents_depth')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="divider_type" :value="__('Divider style')" />
                            <x-select id="divider_type" name="divider_type" class="mt-1 block w-full">
                                @foreach (\App\Enums\DividerType::cases() as $divider)
                                    <option value="{{ $divider->value }}" @selected(old('divider_type', $setting->divider_type->value) === $divider->value)>
                                        {{ $divider->label() }}
                                    </option>
                                @endforeach
                            </x-select>
                            <x-input-error :messages="$errors->get('divider_type')" class="mt-2" />
                        </div>
                    </fieldset>

                    <fieldset x-data="{ appendixOpen: {{ $setting->include_codex_appendix ? 'true' : 'false' }} }" class="space-y-3">
                        <legend class="text-sm font-semibold text-content">{{ __('Appendix') }}</legend>

                        <label for="include_codex_appendix" class="flex items-start gap-3">
                            <input
                                type="checkbox"
                                id="include_codex_appendix"
                                name="include_codex_appendix"
                                value="1"
                                x-model="appendixOpen"
                                @checked(old('include_codex_appendix', $setting->include_codex_appendix))
                                class="mt-1 rounded-sm border-border-strong text-link shadow-xs focus:ring-focus"
                            >
                            <span class="text-sm text-content-muted">{{ __('Include codex appendix') }}</span>
                        </label>
                        <x-input-error :messages="$errors->get('include_codex_appendix')" class="mt-1" />

                        <div x-show="appendixOpen" class="ms-7 space-y-3">
                            <p class="text-sm font-medium text-content-muted">{{ __('Which entry types') }}</p>

                            @php $checkedTypes = old('appendix_entry_types', $setting->appendix_entry_types ?? []); @endphp
                            @foreach (\App\Enums\CodexEntryType::cases() as $type)
                                <label for="appendix_entry_type_{{ $type->value }}" class="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        id="appendix_entry_type_{{ $type->value }}"
                                        name="appendix_entry_types[]"
                                        value="{{ $type->value }}"
                                        @checked(in_array($type->value, $checkedTypes, true))
                                        class="rounded-sm border-border-strong text-link shadow-xs focus:ring-focus"
                                    >
                                    <span class="text-sm text-content-muted">{{ $type->pluralLabel() }}</span>
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
                                    class="mt-1 rounded-sm border-border-strong text-link shadow-xs focus:ring-focus"
                                >
                                <span class="text-sm text-content-muted">{{ __('Include images') }}</span>
                            </label>
                        </div>
                    </fieldset>

                    <x-button variant="primary">{{ __('Save configuration') }}</x-button>
                </form>

                {{--
                    Section order: the fixed reading
                    order the enabled sections render in. `title` is pinned
                    first; every other entry moves independently of its
                    include-toggle above. Each move is its own PATCH, exactly
                    like Act/Chapter/Scene reordering — not part of the
                    "Save configuration" form.
                --}}
                <div class="mt-8 max-w-2xl">
                    <x-heading level="4">{{ __('Section order') }}</x-heading>
                    <p class="mt-1 text-sm text-content-muted">
                        {{ __('The order enabled sections render in. A section only renders when it is both enabled above and has content.') }}
                    </p>

                    <ul class="mt-3 divide-y divide-border border border-border rounded-md">
                        @foreach ($sectionOrder as $sectionKey)
                            <li class="flex items-center justify-between px-4 py-2">
                                <span class="text-sm text-content-muted">{{ ucfirst(str_replace('_', ' ', $sectionKey)) }}</span>
                                <div class="flex gap-1">
                                    <x-icon-move-button direction="up"
                                        :action="route('admin.data.publication-settings.section-order.move-up', ['project' => $selectedProject, 'section' => $sectionKey])"
                                        :disabled="$sectionKey === \App\Models\PublicationSetting::PINNED_FIRST_SECTION || $loop->index <= 1"
                                    />
                                    <x-icon-move-button direction="down"
                                        :action="route('admin.data.publication-settings.section-order.move-down', ['project' => $selectedProject, 'section' => $sectionKey])"
                                        :disabled="$sectionKey === \App\Models\PublicationSetting::PINNED_FIRST_SECTION || $loop->last"
                                    />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{--
                    Download exports using the SAVED settings above, so it is a
                    separate form/button and not part of the config form's own
                    submit.
                --}}
                <form method="POST" action="{{ route('admin.data.export.epub') }}" class="mt-8 max-w-lg">
                    @csrf
                    <input type="hidden" name="project_id" value="{{ $selectedProject->id }}">
                    <x-button variant="primary">{{ __('Download EPUB') }}</x-button>
                    <p class="mt-2 text-xs text-content-muted">{{ __('Exports using the saved configuration above.') }}</p>
                </form>

                <p class="mt-4 text-xs text-content-muted">
                    {{ __('For full EPUB conformance verification, validate the downloaded file with the official') }}
                    <a href="https://www.w3.org/publishing/epubcheck/" class="text-link underline hover:text-link-hover focus:outline-hidden focus:ring-2 focus:ring-focus focus:ring-offset-2 rounded-xs" target="_blank" rel="noopener">
                        {{ __('epubcheck') }}
                    </a>
                    {{ __('tool.') }}
                </p>
                </fieldset>
            @endif
        @endif
    </x-card>
</x-admin-layout>
