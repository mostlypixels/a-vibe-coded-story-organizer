@props([
    'name',
    'id' => null,
    'value' => '',
    'rows' => 4,
    'minHeight' => null,
    'placeholder' => '',
    'disabled' => false,
])

@php
    // The single reuse point that replaces the rich-HTML textareas. Progressive
    // enhancement: a real <textarea> holds the value and submits with JS off; Alpine
    // (see resources/js/wysiwyg.js) mounts the Tiptap editor over it, hydrates from
    // it, and syncs edits back before submit. Pre-mount state is hidden with
    // style="display:none" (no x-cloak), matching the other interactive components.
    $id = $id ?? $name;
    // Give the editable region roughly the height of the textarea it replaces.
    $resolvedMinHeight = $minHeight ?? (($rows * 1.5) + 1).'rem';

    // Simple toggle buttons: [label, command, active-name]. Headings, link and the
    // horizontal rule are handled separately below (they take arguments / prompts).
    $toggles = [
        ['B', 'toggleBold', 'bold', __('Bold')],
        ['I', 'toggleItalic', 'italic', __('Italic')],
        ['U', 'toggleUnderline', 'underline', __('Underline')],
        ['S', 'toggleStrike', 'strike', __('Strikethrough')],
        ['&bull;', 'toggleBulletList', 'bulletList', __('Bulleted list')],
        ['1.', 'toggleOrderedList', 'orderedList', __('Numbered list')],
        ['&rdquo;', 'toggleBlockquote', 'blockquote', __('Blockquote')],
        ['&lt;/&gt;', 'toggleCode', 'code', __('Inline code')],
        ['{ }', 'toggleCodeBlock', 'codeBlock', __('Code block')],
    ];

    $btnBase = 'inline-flex min-w-[2rem] items-center justify-center rounded px-2 py-1 text-sm font-medium';
@endphp

<div
    x-data="wysiwyg({
        disabled: {{ $disabled ? 'true' : 'false' }},
        placeholder: @js($placeholder),
        minHeight: @js($resolvedMinHeight),
        linkPrompt: @js(__('Enter a URL (http:// or https://)')),
    })"
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
                </div>
            @endunless

            <div x-ref="editor"></div>
        </div>
    </div>
</div>
