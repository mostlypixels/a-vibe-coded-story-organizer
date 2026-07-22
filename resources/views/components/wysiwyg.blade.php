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

    // Heading level buttons (H1-H4), collapsed into the Headings dropdown. Each drives
    // x-wysiwyg.toolbar-button with the plain cmd(command, args) + isOn(active, args)
    // shape.
    $headings = collect(range(1, 4))->map(fn ($level) => [
        'label' => "H{$level}",
        'command' => 'toggleHeading',
        'args' => ['level' => $level],
        'active' => ['heading', ['level' => $level]],
        'title' => __('Heading :level', ['level' => $level]),
    ]);

    // The Headings dropdown trigger shows the active level's label (H1-H4) when the
    // cursor sits in a heading, or a plain "H" otherwise — built as a nested-ternary JS
    // expression from the $headings array above so it stays in sync with it. Built
    // right-to-left (reverse order) so the final expression checks level 1 first.
    $headingTriggerLabelExpr = Illuminate\Support\Js::from('H');
    foreach ($headings->reverse() as $heading) {
        $headingTriggerLabelExpr = "isOn('heading', ".Illuminate\Support\Js::from($heading['args']).') ? '
            .Illuminate\Support\Js::from($heading['label']).' : ('.$headingTriggerLabelExpr.')';
    }
    $headingTriggerActiveExpr = $headings
        ->map(fn ($heading) => "isOn('heading', ".Illuminate\Support\Js::from($heading['args']).')')
        ->implode(' || ');

    // Text format: Bold/Italic/Underline/Strike. Underline/Strike round-trip in both
    // formats (expand-tip-tap task 05: Strike is standard GFM; Underline serializes via
    // the sanctioned `<u>` HTML-passthrough exception, see resources/js/wysiwyg.js's
    // MarkdownUnderline), so both join the unconditional base array — no per-format
    // gate needed, matching the slash menu.
    $textFormat = [
        ['label' => 'B', 'command' => 'toggleBold', 'active' => ['bold'], 'title' => __('Bold')],
        ['label' => 'I', 'command' => 'toggleItalic', 'active' => ['italic'], 'title' => __('Italic')],
        ['label' => 'U', 'command' => 'toggleUnderline', 'active' => ['underline'], 'title' => __('Underline')],
        ['label' => 'S', 'command' => 'toggleStrike', 'active' => ['strike'], 'title' => __('Strikethrough')],
    ];

    // Lists & blocks: bullet/ordered/task list, blockquote, inline code, code block.
    $listsAndBlocks = [
        ['label' => '&bull;', 'command' => 'toggleBulletList', 'active' => ['bulletList'], 'title' => __('Bulleted list')],
        ['label' => '1.', 'command' => 'toggleOrderedList', 'active' => ['orderedList'], 'title' => __('Numbered list')],
        // Task list: same plain-toggle shape as the two list types above — no
        // isMarkdown gate needed on the toggle itself (task lists round-trip in both
        // formats, expand-tip-tap task 03); only resize/merge are format-gated below.
        ['label' => '&#9744;', 'command' => 'toggleTaskList', 'active' => ['taskList'], 'title' => __('Task list')],
        ['label' => '&rdquo;', 'command' => 'toggleBlockquote', 'active' => ['blockquote'], 'title' => __('Blockquote')],
        ['label' => '&lt;/&gt;', 'command' => 'toggleCode', 'active' => ['code'], 'title' => __('Inline code')],
        ['label' => '{ }', 'command' => 'toggleCodeBlock', 'active' => ['codeBlock'], 'title' => __('Code block')],
    ];

    // Table structure: row/column ops (both formats) plus merge/split (HTML-mode only),
    // collapsed into the Table structure dropdown. Row/column ops keep the grid
    // rectangular, so — unlike merge/split — they're available in both formats, no
    // @if ($markdown) gate needed.
    $tableStructure = [
        ['label' => '&#8213;+', 'command' => 'addRowAfter', 'title' => __('Add row below')],
        ['label' => '&#8213;&minus;', 'command' => 'deleteRow', 'title' => __('Delete row')],
        ['label' => '&#8214;+', 'command' => 'addColumnAfter', 'title' => __('Add column right')],
        ['label' => '&#8214;&minus;', 'command' => 'deleteColumn', 'title' => __('Delete column')],
    ];

    // Merge/split-cell: HTML-mode fields only. A merged cell (colspan) is lossless
    // there but loses its structure in Markdown, so this affordance is not rendered at
    // all for Markdown-mode fields — prevent, don't just warn, per architecture.md §2.
    if (! $markdown) {
        $tableStructure[] = ['label' => '&#8676;&#8677;', 'command' => 'mergeCells', 'title' => __('Merge cells')];
        $tableStructure[] = ['label' => '&#8677;&#8676;', 'command' => 'splitCell', 'title' => __('Split cell')];
    }

    $btnBase = 'inline-flex min-w-[2rem] items-center justify-center rounded px-2 py-1 text-sm font-medium';
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
        {{ $attributes->merge(['class' => 'block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm']) }}
    >{{ $value }}</textarea>

    {{-- Editor UI: hidden until Alpine mounts (style="display:none", no x-cloak). --}}
    <div x-show="ready" style="display: none;">
        <div class="overflow-hidden rounded-md border border-gray-300 shadow-sm focus-within:border-ocean-500 focus-within:ring-1 focus-within:ring-ocean-500">
            @unless ($disabled)
                <div class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 bg-gray-50 px-2 py-1" role="toolbar" aria-label="{{ __('Formatting') }}">
                    {{-- Cluster 1: Headings, collapsed into a dropdown. The trigger's
                         label and active-state highlighting are computed from the same
                         $headings array that populates the dropdown content, so they
                         can't drift out of sync with it. --}}
                    <x-dropdown align="left" width="auto" contentClasses="p-1 bg-white flex items-center gap-0.5">
                        <x-slot name="trigger">
                            <button
                                type="button"
                                :class="({{ $headingTriggerActiveExpr }}) ? 'bg-ocean-100 text-ocean-800' : 'text-gray-600 hover:bg-gray-200'"
                                class="{{ $btnBase }}"
                                title="{{ __('Heading') }}"
                                aria-label="{{ __('Heading') }}"
                            ><span x-text="{{ $headingTriggerLabelExpr }}"></span></button>
                        </x-slot>

                        <x-slot name="content">
                            @foreach ($headings as $heading)
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

                    {{-- Cluster 2: Text format — Bold/Italic/Underline/Strike, unchanged
                         inline row. --}}
                    @foreach ($textFormat as $toggle)
                        <x-wysiwyg.toolbar-button
                            :command="$toggle['command']"
                            :active="$toggle['active']"
                            :label="$toggle['label']"
                            :title="$toggle['title']"
                        />
                    @endforeach

                    <span class="mx-1 h-5 w-px bg-gray-300"></span>

                    {{-- Cluster 3: Lists & blocks, unchanged inline row. --}}
                    @foreach ($listsAndBlocks as $toggle)
                        <x-wysiwyg.toolbar-button
                            :command="$toggle['command']"
                            :active="$toggle['active']"
                            :label="$toggle['label']"
                            :title="$toggle['title']"
                        />
                    @endforeach

                    <span class="mx-1 h-5 w-px bg-gray-300"></span>

                    {{-- Cluster 4: Insert — Link, Horizontal rule, Table, Image, Callout.
                         Table and Image live here now (moved up from after the
                         table-structure ops) so every "insert something new" action sits
                         together. Link/Image/Callout call bespoke no-arg helpers rather
                         than cmd(), so they stay hand-written instead of using
                         x-wysiwyg.toolbar-button. --}}
                    <button
                        type="button"
                        @click="setLink()"
                        :class="isOn('link') ? 'bg-ocean-100 text-ocean-800' : 'text-gray-600 hover:bg-gray-200'"
                        class="{{ $btnBase }}"
                        title="{{ __('Link') }}"
                        aria-label="{{ __('Link') }}"
                    >&#128279;</button>

                    <button
                        type="button"
                        @click="cmd('setHorizontalRule')"
                        class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                        title="{{ __('Horizontal rule') }}"
                        aria-label="{{ __('Horizontal rule') }}"
                    >&mdash;</button>

                    <button
                        type="button"
                        @click="cmd('insertTable', { rows: 3, cols: 3, withHeaderRow: true })"
                        class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                        title="{{ __('Table') }}"
                        aria-label="{{ __('Table') }}"
                    >&#9638;</button>

                    <button
                        type="button"
                        @click="setImage()"
                        class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                        title="{{ __('Image') }}"
                        aria-label="{{ __('Image') }}"
                    >&#128247;</button>

                    {{-- Callout (`> [!TYPE]`): available in both formats (expand-tip-tap
                         task 06 — callouts round-trip in both). Clicking inserts a note
                         callout, or cycles the type of the callout the cursor is in. --}}
                    <button
                        type="button"
                        @click="toggleCallout()"
                        :class="isOn('callout') ? 'bg-ocean-100 text-ocean-800' : 'text-gray-600 hover:bg-gray-200'"
                        class="{{ $btnBase }}"
                        title="{{ __('Callout') }}"
                        aria-label="{{ __('Callout') }}"
                    >&#9432;</button>

                    <span class="mx-1 h-5 w-px bg-gray-300"></span>

                    {{-- Cluster 5: Table structure, collapsed into a dropdown. Its
                         trigger glyph (square + pencil) is deliberately distinct from
                         cluster 4's plain-square "insert table" glyph so the two aren't
                         confused, and its title/aria-label reads "Table structure" vs.
                         cluster 4's "Table". Merge/split still only appear when
                         ! $markdown — see the $tableStructure array above. --}}
                    <x-dropdown align="left" width="auto" contentClasses="p-1 bg-white flex items-center gap-0.5">
                        <x-slot name="trigger">
                            <button
                                type="button"
                                class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                                title="{{ __('Table structure') }}"
                                aria-label="{{ __('Table structure') }}"
                            >&#9638;&#9998;</button>
                        </x-slot>

                        <x-slot name="content">
                            @foreach ($tableStructure as $op)
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
