@props([
    'name',
    'id' => null,
    'value' => '',
    'rows' => 4,
    'minHeight' => null,
    'placeholder' => '',
    'disabled' => false,
    'markdown' => false,
])

@php
    $id = $id ?? $name;
    $format = $markdown ? 'markdown' : 'html';
    $resolvedMinHeight = $minHeight ?? (($rows * 1.5) + 1).'rem';

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
    {{-- Keep the textarea visible until the editor is ready. --}}
    <x-textarea
        x-ref="textarea"
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        :disabled="$disabled"
        x-show="! ready"
        {{ $attributes->merge(['class' => 'block w-full']) }}
    >{{ $value }}</x-textarea>

    {{-- Do not clip this wrapper because toolbar menus extend over the editor. --}}
    <div x-show="ready" style="display: none;">
        <div class="rounded-md border border-border-strong shadow-xs focus-within:border-focus focus-within:ring-1 focus-within:ring-focus">
            @unless ($disabled)
                <div class="flex flex-wrap items-center gap-0.5 rounded-t-md border-b border-border bg-surface-sunken px-2 py-1" role="toolbar" aria-label="{{ __('Formatting') }}">
                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->styles()"
                        trigger-icon="pilcrow"
                        :title="__('Style')"
                        active-expression="isOn('heading') || isOn('blockquote')"
                    />

                    <span class="mx-1 h-5 w-px bg-border"></span>

                    @foreach ($toolbar->textFormat() as $toggle)
                        <x-wysiwyg.toolbar-button
                            :command="$toggle['command']"
                            :active="$toggle['active']"
                            :title="$toggle['title']"
                        ><x-dynamic-component :component="'tabler-'.$toggle['icon']" class="h-4 w-4" /></x-wysiwyg.toolbar-button>
                    @endforeach

                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->typography()"
                        trigger-icon="typography"
                        :title="__('Typography')"
                        active-expression="isOn('strike') || isOn('subscript') || isOn('superscript')"
                    />

                    <span class="mx-1 h-5 w-px bg-border"></span>

                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->lists()"
                        trigger-icon="list"
                        :title="__('Lists')"
                        active-expression="isOn('bulletList') || isOn('orderedList') || isOn('taskList')"
                    />

                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->callouts()"
                        trigger-icon="alert-square"
                        :title="__('Callout')"
                        active-expression="isOn('callout')"
                    />

                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->code()"
                        trigger-icon="code"
                        :title="__('Code')"
                        active-expression="isOn('code') || isOn('codeBlock')"
                    />

                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->alignment()"
                        trigger-icon="align-left"
                        :title="__('Align')"
                        :active-expression="$toolbar->alignmentActiveExpression()"
                    />

                    <x-wysiwyg.toolbar-dropdown
                        :items="$toolbar->textColor()"
                        trigger-icon="palette"
                        :title="__('Colour')"
                        :active-expression="$toolbar->textColorActiveExpression()"
                    />

                    <span class="mx-1 h-5 w-px bg-border"></span>

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
