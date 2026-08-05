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
    })"
    data-format="{{ $format }}"
    class="mt-1"
>
    {{-- No-JS fallback: submits raw (still sanitized server-side); Alpine hides it once the editor mounts. --}}
    <x-textarea
        x-ref="textarea"
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        :disabled="$disabled"
        x-show="! ready"
        {{ $attributes->merge(['class' => 'block w-full']) }}
    >{{ $value }}</x-textarea>

    {{-- Editor UI: hidden until Alpine mounts (style="display:none", no x-cloak). The
         toolbar carries its own top rounding (rounded-t-md) rather than the old
         overflow-hidden on this wrapper: overflow-hidden clipped any dropdown panel
         that overflowed past the editor below it, cropping the panel instead of
         layering over it (a z-index can't win against an ancestor's overflow clip).
         The editor's own mount point paints no background (see resources/js/wysiwyg.js
         editorProps — `.prose` sets only text/border colours), so it needs no
         matching rounded-b-md to avoid the same problem from the other side. --}}
    <div x-show="ready" style="display: none;">
        <div class="rounded-md border border-border-strong shadow-xs focus-within:border-focus focus-within:ring-1 focus-within:ring-focus">
            @unless ($disabled)
                <div class="flex flex-wrap items-center gap-0.5 rounded-t-md border-b border-border bg-surface-sunken px-2 py-1" role="toolbar" aria-label="{{ __('Formatting') }}">
                    {{-- Style — block-level "what is this" choice: Paragraph, Blockquote,
                         H1..H4, collapsed into a dropdown. A static pilcrow (¶) glyph
                         rather than a dynamic level label, since the dropdown no longer
                         only offers headings — see WysiwygToolbar::styles(). --}}
                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->styles()"
                        trigger-icon="pilcrow"
                        :title="__('Style')"
                        active-expression="isOn('heading') || isOn('blockquote')"
                    />

                    <span class="mx-1 h-5 w-px bg-border"></span>

                    {{-- Text format — Bold/Italic/Underline, the three permanent flat
                         buttons; less-common decorations live in the typography
                         dropdown below — see WysiwygToolbar::textFormat(). --}}
                    @foreach ($toolbar->textFormat() as $toggle)
                        <x-wysiwyg.toolbar-button
                            :command="$toggle['command']"
                            :active="$toggle['active']"
                            :title="$toggle['title']"
                        ><x-dynamic-component :component="'tabler-'.$toggle['icon']" class="h-4 w-4" /></x-wysiwyg.toolbar-button>
                    @endforeach

                    {{-- Typography — Strikethrough/Subscript/Superscript, collapsed
                         into a dropdown — see WysiwygToolbar::typography(). --}}
                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->typography()"
                        trigger-icon="typography"
                        :title="__('Typography')"
                        active-expression="isOn('strike') || isOn('subscript') || isOn('superscript')"
                    />

                    <span class="mx-1 h-5 w-px bg-border"></span>

                    {{-- Lists — Bulleted, Numbered, Task, collapsed into a dropdown
                         instead of three flat buttons. --}}
                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->lists()"
                        trigger-icon="list"
                        :title="__('Lists')"
                        active-expression="isOn('bulletList') || isOn('orderedList') || isOn('taskList')"
                    />

                    {{-- Callout (`> [!TYPE]`) as a dropdown of the five labeled types
                         instead of a single glyph button that cycled through them.
                         Clicking a type inserts a new callout, or changes the type of
                         the one the cursor is already in — see setCalloutType() in
                         wysiwyg.js. Available in both formats. --}}
                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->callouts()"
                        trigger-icon="alert-square"
                        :title="__('Callout')"
                        active-expression="isOn('callout')"
                    />

                    {{-- Code — Inline code, Code block, collapsed into a dropdown
                         instead of two flat buttons. --}}
                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->code()"
                        trigger-icon="code"
                        :title="__('Code')"
                        active-expression="isOn('code') || isOn('codeBlock')"
                    />

                    <span class="mx-1 h-5 w-px bg-border"></span>

                    {{-- Insert — Link, Horizontal rule, Image. Link and Image call
                         bespoke no-arg helpers rather than cmd(), so they pass `action`
                         (a raw JS expression) instead of `command`. --}}
                    <x-wysiwyg.toolbar-button
                        action="setLink()"
                        :active="['link']"
                        :title="__('Link')"
                    ><x-tabler-link class="h-4 w-4" /></x-wysiwyg.toolbar-button>

                    <x-wysiwyg.toolbar-button
                        command="setHorizontalRule"
                        :title="__('Horizontal rule')"
                    ><x-tabler-minus class="h-4 w-4" /></x-wysiwyg.toolbar-button>

                    <x-wysiwyg.toolbar-button
                        action="setImage()"
                        :title="__('Image')"
                    ><x-tabler-photo class="h-4 w-4" /></x-wysiwyg.toolbar-button>

                    <span class="mx-1 h-5 w-px bg-border"></span>

                    {{-- Table, one dropdown for the whole concern — insert, then
                         row/column ops, then merge/split for HTML-mode fields only.
                         Previously split into a plain "insert" button here plus a
                         separate "structure" dropdown elsewhere in the toolbar; see
                         WysiwygToolbar::table(). --}}
                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->table()"
                        trigger-icon="table"
                        :title="__('Table')"
                    />
                </div>
            @endunless

            <div x-ref="editor"></div>
        </div>
    </div>
</div>
