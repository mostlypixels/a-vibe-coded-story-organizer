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

    // Simple toggle buttons: [label, command, active-name]. Headings, link and the
    // horizontal rule are handled separately below (they take arguments / prompts).
    // Underline/Strike round-trip in both formats (expand-tip-tap task 05: Strike is
    // standard GFM; Underline serializes via the sanctioned `<u>` HTML-passthrough
    // exception, see resources/js/wysiwyg.js's MarkdownUnderline), so both join the
    // unconditional base array — no per-format gate needed, matching the slash menu.
    $toggles = [
        ['B', 'toggleBold', 'bold', __('Bold')],
        ['I', 'toggleItalic', 'italic', __('Italic')],
        ['U', 'toggleUnderline', 'underline', __('Underline')],
        ['S', 'toggleStrike', 'strike', __('Strikethrough')],
    ];

    $toggles = array_merge($toggles, [
        ['&bull;', 'toggleBulletList', 'bulletList', __('Bulleted list')],
        ['1.', 'toggleOrderedList', 'orderedList', __('Numbered list')],
        // Task list: same plain-toggle shape as the two list types above — no
        // isMarkdown gate needed on the toggle itself (task lists round-trip in both
        // formats, expand-tip-tap task 03); only resize/merge are format-gated below.
        ['&#9744;', 'toggleTaskList', 'taskList', __('Task list')],
        ['&rdquo;', 'toggleBlockquote', 'blockquote', __('Blockquote')],
        ['&lt;/&gt;', 'toggleCode', 'code', __('Inline code')],
        ['{ }', 'toggleCodeBlock', 'codeBlock', __('Code block')],
    ]);

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
                    @foreach (range(1, 4) as $level)
                        <button
                            type="button"
                            @click="cmd('toggleHeading', { level: {{ $level }} })"
                            :class="isOn('heading', { level: {{ $level }} }) ? 'bg-ocean-100 text-ocean-800' : 'text-gray-600 hover:bg-gray-200'"
                            class="{{ $btnBase }}"
                            title="{{ __('Heading :level', ['level' => $level]) }}"
                            aria-label="{{ __('Heading :level', ['level' => $level]) }}"
                        >H{{ $level }}</button>
                    @endforeach

                    <span class="mx-1 h-5 w-px bg-gray-300"></span>

                    @foreach ($toggles as [$label, $command, $active, $title])
                        <button
                            type="button"
                            @click="cmd('{{ $command }}')"
                            :class="isOn('{{ $active }}') ? 'bg-ocean-100 text-ocean-800' : 'text-gray-600 hover:bg-gray-200'"
                            class="{{ $btnBase }}"
                            title="{{ $title }}"
                            aria-label="{{ $title }}"
                        >{!! $label !!}</button>
                    @endforeach

                    <span class="mx-1 h-5 w-px bg-gray-300"></span>

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

                    <span class="mx-1 h-5 w-px bg-gray-300"></span>

                    {{-- Table/Image take arguments/prompts rather than being plain toggles,
                         so — like Link and Horizontal rule above — they get their own
                         buttons rather than a $toggles entry. --}}
                    <button
                        type="button"
                        @click="cmd('insertTable', { rows: 3, cols: 3, withHeaderRow: true })"
                        class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                        title="{{ __('Table') }}"
                        aria-label="{{ __('Table') }}"
                    >&#9638;</button>

                    {{-- Row/column add-remove: unlike merge/split, these keep the grid
                         rectangular (every row still has the same cell count), which GFM
                         tables always support — so, unlike merge/split below, this is
                         available in both formats, no @if ($markdown) gate needed. --}}
                    <button
                        type="button"
                        @click="cmd('addRowAfter')"
                        class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                        title="{{ __('Add row below') }}"
                        aria-label="{{ __('Add row below') }}"
                    >&#8213;+</button>

                    <button
                        type="button"
                        @click="cmd('deleteRow')"
                        class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                        title="{{ __('Delete row') }}"
                        aria-label="{{ __('Delete row') }}"
                    >&#8213;&minus;</button>

                    <button
                        type="button"
                        @click="cmd('addColumnAfter')"
                        class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                        title="{{ __('Add column right') }}"
                        aria-label="{{ __('Add column right') }}"
                    >&#8214;+</button>

                    <button
                        type="button"
                        @click="cmd('deleteColumn')"
                        class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                        title="{{ __('Delete column') }}"
                        aria-label="{{ __('Delete column') }}"
                    >&#8214;&minus;</button>

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

                    @if (! $markdown)
                        <span class="mx-1 h-5 w-px bg-gray-300"></span>

                        {{-- Merge/split-cell: HTML-mode fields only. A merged cell (colspan)
                             is lossless there but loses its structure in Markdown, so this
                             affordance is not rendered at all for Markdown-mode fields —
                             prevent, don't just warn, per architecture.md §2. There is no
                             existing merge/split UI to gate; this is new either way. --}}
                        <button
                            type="button"
                            @click="cmd('mergeCells')"
                            class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                            title="{{ __('Merge cells') }}"
                            aria-label="{{ __('Merge cells') }}"
                        >&#8676;&#8677;</button>

                        <button
                            type="button"
                            @click="cmd('splitCell')"
                            class="{{ $btnBase }} text-gray-600 hover:bg-gray-200"
                            title="{{ __('Split cell') }}"
                            aria-label="{{ __('Split cell') }}"
                        >&#8677;&#8676;</button>
                    @endif
                </div>
            @endunless

            <div x-ref="editor"></div>
        </div>
    </div>
</div>
