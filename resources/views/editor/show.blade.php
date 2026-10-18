@php
    $rail = '2.5rem';
    $leftColumn = $layout['left']['collapsed'] ? $rail : $layout['left']['width'].'px';
    $rightColumn = $layout['right']['collapsed'] ? $rail : $layout['right']['width'].'px';
@endphp

<x-app-layout full-height>
    {{-- Below `lg`, stored widths do not apply: both panels are rails, and an open one is a drawer. --}}
    <div
        class="relative grid h-full grid-cols-[2.5rem_minmax(0,1fr)_2.5rem] lg:grid-cols-[min(var(--editor-left,2.5rem),40vw)_minmax(0,1fr)_min(var(--editor-right,2.5rem),40vw)]"
        style="--editor-left: {{ $leftColumn }}; --editor-right: {{ $rightColumn }};"
        x-data="editorLayout({
            layout: @js($layout),
            defaults: @js(config('editor.layout')),
            minWidth: @js(config('editor.min_width')),
            maxWidth: @js(config('editor.max_width')),
            saveUrl: @js(route('preferences.editor-layout')),
        })"
        x-bind:style="columns()"
        x-bind:class="drag ? 'cursor-col-resize select-none' : ''"
        x-on:keydown.escape.window="closeDrawer()"
        x-on:editor-section-toggle="setSection($event.detail.section, $event.detail.open)"
        data-editor-layout
    >
        <div
            class="absolute inset-0 z-20 bg-black/40 lg:hidden"
            x-show="drawer !== null"
            x-on:click="closeDrawer()"
            style="display: none;"
            aria-hidden="true"
            data-editor-scrim
        ></div>

        <x-editor-panel side="left" :label="__('Scenes')" :collapsed="$layout['left']['collapsed']" :width="$layout['left']['width']">
            @include('editor.partials.recent-scenes')
        </x-editor-panel>

        {{-- The scroll box of the prose. The sticky toolbar sticks to its top. --}}
        <section class="min-h-0 overflow-y-auto" aria-labelledby="editor-scene-title" data-editor-centre data-position-scroll>
            <div class="mx-auto md:max-w-(--page-width) px-4 py-6">
                <header class="mb-4 space-y-3">
                    @include('editor.partials.scene-pager')

                    <div class="flex items-start justify-between gap-2">
                        <h1 id="editor-scene-title" class="text-2xl font-bold leading-tight text-content">{{ $scene->name }}</h1>
                        <x-icon-edit-link :href="route('scenes.edit', $scene)" class="shrink-0" />
                    </div>

                    <p class="mt-1 text-sm text-content-muted" data-editor-context>
                        @foreach ($context as $segment)
                            @unless ($loop->first)
                                <span aria-hidden="true">&rsaquo;</span>
                            @endunless
                            <span>{{ $segment }}</span>
                        @endforeach
                    </p>

                    @include('editor.partials.today')
                </header>

                <x-autosave-field
                    entity="scene"
                    :model="$scene"
                    field="contents"
                    :label="__('Contents')"
                    :rows="20"
                    :quick-codex-entry="true"
                    :sticky-toolbar="true"
                    :autofocus="true"
                    :position-key="'editor-position:'.$scene->id"
                />

                <p class="mt-2 text-sm text-success" data-codex-entry-created role="status" aria-live="polite"></p>
            </div>
        </section>

        <x-editor-panel side="right" :label="__('Codex and notes')" :collapsed="$layout['right']['collapsed']" :width="$layout['right']['width']">
            <div class="space-y-6">
                @include('editor.partials.card-section', [
                    'type' => 'codex',
                    'section' => 'codex',
                    'collapsed' => $layout['sections']['codex'],
                    'heading' => __('Codex'),
                    'cards' => $cards['codex'],
                    'cardUrl' => route('codex.card', ['codexEntry' => '__ID__', 'scene' => $scene->id]),
                    'viewUrl' => route('codex.show', ['codexEntry' => '__ID__']),
                ])

                @include('editor.partials.card-section', [
                    'type' => 'note',
                    'section' => 'notes',
                    'collapsed' => $layout['sections']['notes'],
                    'heading' => __('Notes'),
                    'cards' => $cards['notes'],
                    'cardUrl' => route('notes.card', ['note' => '__ID__']),
                    'viewUrl' => route('notes.show', ['note' => '__ID__']),
                ])
            </div>
        </x-editor-panel>
    </div>

    @include('scenes.partials.quick-codex-entry', ['scene' => $scene])
</x-app-layout>
