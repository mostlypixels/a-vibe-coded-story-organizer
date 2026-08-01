@props([
    'name',
    'id' => null,
    'value' => '',
    'rows' => 4,
    'minHeight' => null,
    'placeholder' => '',
    'disabled' => false,
    // When true the field stores clean CommonMark (Scene contents) instead of
    // sanitized HTML, and the value serializes to Markdown. See resources/js/wysiwyg.js.
    'markdown' => false,
])

@php
    // The single reuse point that replaces the rich-HTML textareas. Progressive
    // enhancement: a real <textarea> holds the value and submits with JS off; Alpine
    // (see resources/js/wysiwyg.js) mounts the Tiptap editor over it, hydrates from
    // it, and syncs edits back before submit. Pre-mount state is hidden with
    // style="display:none" (no x-cloak), matching the other interactive components.
    $id = $id ?? $name;
    $format = $markdown ? 'markdown' : 'html';
    // Give the editable region roughly the height of the textarea it replaces.
    $resolvedMinHeight = $minHeight ?? (($rows * 1.5) + 1).'rem';

    // Every button definition lives in the support class; this template only lays
    // them out. Merge/split-cell is gated there on $markdown.
    $toolbar = new \App\Support\WysiwygToolbar($markdown);
@endphp

<div
    x-data="wysiwyg({
        disabled: {{ $disabled ? 'true' : 'false' }},
        format: @js($format),
        placeholder: @js($placeholder),
        minHeight: @js($resolvedMinHeight),
        linkPrompt: @js(__('Enter a URL (http:// or https://)')),
        imagePrompt: @js(__('Enter an image URL (http:// or https://)')),
        imageAltPrompt: @js(__('Alt text (optional, for accessibility)')),
        headingLevels: @js(\App\Support\WysiwygToolbar::HEADING_LEVELS),
    })"
    data-format="{{ $format }}"
    class="mt-1"
>
    {{-- No-JS fallback: submits raw (still sanitized server-side); Alpine hides it once the editor mounts. --}}
    <textarea
        x-ref="textarea"
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @disabled($disabled)
        x-show="! ready"
        {{ $attributes->merge(['class' => 'block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-2xs']) }}
    >{{ $value }}</textarea>

    {{-- Editor UI: hidden until Alpine mounts (style="display:none", no x-cloak). --}}
    <div x-show="ready" style="display: none;">
        <div class="overflow-hidden rounded-md border border-gray-300 shadow-2xs focus-within:border-ocean-500 focus-within:ring-1 focus-within:ring-ocean-500">
            @unless ($disabled)
                <div class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 bg-gray-50 px-2 py-1" role="toolbar" aria-label="{{ __('Formatting') }}">
                    {{-- Cluster 1: Headings, collapsed into a dropdown. The trigger's
                         label and highlight come from headingLabel()/headingLevel() in
                         wysiwyg.js, driven by the same HEADING_LEVELS that fill the
                         dropdown, so the two can't drift out of sync. --}}
                    <x-dropdown align="left" width="auto" contentClasses="p-1 bg-white flex items-center gap-0.5">
                        <x-slot name="trigger">
                            <x-wysiwyg.toolbar-button
                                active-expression="headingLevel() !== null"
                                :title="__('Heading')"
                            ><span x-text="headingLabel()"></span></x-wysiwyg.toolbar-button>
                        </x-slot>

                        <x-slot name="content">
                            @foreach ($toolbar->headings() as $heading)
                                <x-wysiwyg.toolbar-button
                                    :command="$heading['command']"
                                    :args="$heading['args']"
                                    :active="$heading['active']"
                                    :label="$heading['label']"
                                    :title="$heading['title']"
                                />
                            @endforeach
                        </x-slot>
                    </x-dropdown>

                    <span class="mx-1 h-5 w-px bg-gray-300"></span>

                    {{-- Cluster 2: Text format — Bold/Italic/Underline/Strike. --}}
                    @foreach ($toolbar->textFormat() as $toggle)
                        <x-wysiwyg.toolbar-button
                            :command="$toggle['command']"
                            :active="$toggle['active']"
                            :label="$toggle['label']"
                            :title="$toggle['title']"
                        />
                    @endforeach

                    <span class="mx-1 h-5 w-px bg-gray-300"></span>

                    {{-- Cluster 3: Lists & blocks. --}}
                    @foreach ($toolbar->listsAndBlocks() as $toggle)
                        <x-wysiwyg.toolbar-button
                            :command="$toggle['command']"
                            :active="$toggle['active']"
                            :label="$toggle['label']"
                            :title="$toggle['title']"
                        />
                    @endforeach

                    <span class="mx-1 h-5 w-px bg-gray-300"></span>

                    {{-- Cluster 4: Insert — Link, Horizontal rule, Table, Image, Callout.
                         Every "insert something new" action sits together. Link, Image
                         and Callout call bespoke no-arg helpers rather than cmd(), so
                         they pass `action` (a raw JS expression) instead of `command`.
                         Callout (`> [!TYPE]`) is available in both formats; clicking
                         inserts a note callout, or cycles the type of the one the cursor
                         is in. --}}
                    <x-wysiwyg.toolbar-button
                        action="setLink()"
                        :active="['link']"
                        label="&#128279;"
                        :title="__('Link')"
                    />

                    <x-wysiwyg.toolbar-button
                        command="setHorizontalRule"
                        label="&mdash;"
                        :title="__('Horizontal rule')"
                    />

                    <x-wysiwyg.toolbar-button
                        command="insertTable"
                        :args="['rows' => 3, 'cols' => 3, 'withHeaderRow' => true]"
                        label="&#9638;"
                        :title="__('Table')"
                    />

                    <x-wysiwyg.toolbar-button
                        action="setImage()"
                        label="&#128247;"
                        :title="__('Image')"
                    />

                    <x-wysiwyg.toolbar-button
                        action="toggleCallout()"
                        :active="['callout']"
                        label="&#9432;"
                        :title="__('Callout')"
                    />

                    <span class="mx-1 h-5 w-px bg-gray-300"></span>

                    {{-- Cluster 5: Table structure, collapsed into a dropdown. Its
                         trigger glyph (square + pencil) is deliberately distinct from
                         cluster 4's plain-square "insert table" glyph so the two aren't
                         confused, and its title/aria-label reads "Table structure" vs.
                         cluster 4's "Table". Merge/split only appear for HTML-mode
                         fields — see WysiwygToolbar::tableStructure(). --}}
                    <x-dropdown align="left" width="auto" contentClasses="p-1 bg-white flex items-center gap-0.5">
                        <x-slot name="trigger">
                            <x-wysiwyg.toolbar-button
                                label="&#9638;&#9998;"
                                :title="__('Table structure')"
                            />
                        </x-slot>

                        <x-slot name="content">
                            @foreach ($toolbar->tableStructure() as $op)
                                <x-wysiwyg.toolbar-button
                                    :command="$op['command']"
                                    :label="$op['label']"
                                    :title="$op['title']"
                                />
                            @endforeach
                        </x-slot>
                    </x-dropdown>
                </div>
            @endunless

            <div x-ref="editor"></div>
        </div>
    </div>
</div>
