@props([
    'name',
    'id' => null,
    'value' => '',
    'rows' => 4,
    'minHeight' => null,
    'placeholder' => '',
    'disabled' => false,
    'markdown' => false,
    'quickCodexEntry' => false,
    // The project to search for "Link to…". Without it, the editor offers web links only.
    'project' => null,
])

@php
    $id = $id ?? $name;
    $format = $markdown ? 'markdown' : 'html';
    $resolvedMinHeight = $minHeight ?? (($rows * 1.5) + 1).'rem';

    $toolbar = new \App\Support\WysiwygToolbar($markdown);
    // A random name, because one page can show several editors.
    $urlDialog = 'url-dialog-'.Str::lower(Str::random(8));
    $linkTargetDialog = 'link-target-'.Str::lower(Str::random(8));
    $linkTargetsUrl = $project ? route('projects.link-targets', $project) : null;
@endphp

<div
    x-data="wysiwyg({
        disabled: {{ $disabled ? 'true' : 'false' }},
        format: @js($format),
        placeholder: @js($placeholder),
        minHeight: @js($resolvedMinHeight),
        strings: @js(\App\Support\ScriptTranslations::wysiwyg()),
        quickCodexEntry: {{ $quickCodexEntry ? 'true' : 'false' }},
        urlDialog: @js($urlDialog),
        linkTargetsUrl: @js($linkTargetsUrl),
        linkTargetDialog: @js($linkTargetDialog),
        linkTargetFailure: @js(__('The search failed. Check your connection and try again.')),
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

                    @if ($linkTargetsUrl)
                        <x-wysiwyg.toolbar-button
                            action="openLinkTarget()"
                            :title="__('Link to…')"
                        ><x-tabler-file-symlink class="h-4 w-4" /></x-wysiwyg.toolbar-button>
                    @endif

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

    @unless ($disabled)
        {{-- The app dialog, not a browser prompt: it keeps the theme and does not hide the selected text. --}}
        <x-dialog :name="$urlDialog" max-width="md" :labelledby="$urlDialog.'-title'">
            <div class="space-y-4">
                <h3 id="{{ $urlDialog }}-title" class="text-lg font-semibold text-content" x-text="urlForm.kind === 'image' ? @js(__('Image')) : @js(__('Link'))"></h3>

                <div>
                    <x-input-label :for="$urlDialog.'-url'" :value="__('Web address (http:// or https://)')" />
                    <x-text-input
                        :id="$urlDialog.'-url'"
                        type="text"
                        inputmode="url"
                        autocomplete="off"
                        class="mt-1 block w-full"
                        x-model="urlForm.url"
                        x-on:keydown.enter.prevent="applyUrl()"
                    />
                    <p x-show="urlForm.error" x-text="urlForm.error" role="alert" class="mt-2 text-sm text-danger-surface-content"></p>
                </div>

                <div x-show="urlForm.kind === 'image'">
                    <x-input-label :for="$urlDialog.'-alt'" :value="__('Alt text (optional, for accessibility)')" />
                    <x-text-input
                        :id="$urlDialog.'-alt'"
                        type="text"
                        autocomplete="off"
                        class="mt-1 block w-full"
                        x-model="urlForm.alt"
                        x-on:keydown.enter.prevent="applyUrl()"
                    />
                </div>
            </div>

            <x-slot name="footer">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                <x-button type="button" x-on:click="applyUrl()">{{ __('Apply') }}</x-button>
            </x-slot>
        </x-dialog>

        @if ($linkTargetsUrl)
            {{-- The search box comes first, so the dialog opens with focus in it. --}}
            <x-dialog :name="$linkTargetDialog" max-width="md" :labelledby="$linkTargetDialog.'-title'">
                <div class="space-y-4" data-link-target-dialog>
                    <h3 id="{{ $linkTargetDialog }}-title" class="text-lg font-semibold text-content">{{ __('Link to…') }}</h3>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div class="grow">
                            <x-input-label :for="$linkTargetDialog.'-query'" :value="__('Search')" />
                            <x-text-input
                                :id="$linkTargetDialog.'-query'"
                                type="search"
                                autocomplete="off"
                                class="mt-1 block w-full"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="true"
                                :aria-controls="$linkTargetDialog.'-results'"
                                x-model="linkTarget.query"
                                x-on:input="linkTarget.queueSearch()"
                                x-on:keydown.arrow-down.prevent="linkTarget.move(1)"
                                x-on:keydown.arrow-up.prevent="linkTarget.move(-1)"
                                x-on:keydown.enter.prevent="pickLinkTarget(linkTarget.highlightedResult())"
                                x-bind:aria-activedescendant="linkTarget.results.length ? '{{ $linkTargetDialog }}-option-' + linkTarget.highlighted : null"
                            />
                        </div>

                        <div>
                            <x-input-label :for="$linkTargetDialog.'-type'" :value="__('Type')" />
                            <x-select :id="$linkTargetDialog.'-type'" class="mt-1 block w-full sm:text-sm" x-model="linkTarget.type" x-on:change="linkTarget.search()">
                                @foreach (\App\Enums\LinkTargetType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ __($type->label()) }}</option>
                                @endforeach
                            </x-select>
                        </div>
                    </div>

                    <ul id="{{ $linkTargetDialog }}-results" role="listbox" class="max-h-64 space-y-1 overflow-y-auto" aria-label="{{ __('Results') }}" x-bind:aria-busy="linkTarget.searching">
                        <template x-for="(candidate, index) in linkTarget.results" x-bind:key="candidate.type + ':' + candidate.id">
                            <li
                                role="option"
                                x-bind:id="'{{ $linkTargetDialog }}-option-' + index"
                                x-bind:aria-selected="(index === linkTarget.highlighted).toString()"
                                x-bind:class="index === linkTarget.highlighted ? 'bg-accent-surface text-accent-content' : 'text-content hover:bg-neutral'"
                                class="cursor-pointer rounded-md px-2 py-1 text-sm"
                                x-text="candidate.label"
                                x-on:mousemove="linkTarget.highlighted = index"
                                x-on:click="pickLinkTarget(candidate)"
                            ></li>
                        </template>
                    </ul>
                    <p class="px-2 text-sm text-content-muted" x-show="!linkTarget.searching && !linkTarget.error && linkTarget.results.length === 0" style="display: none;">{{ __('Nothing found.') }}</p>

                    <p class="text-sm text-danger" x-show="linkTarget.error" style="display: none;" role="alert" x-text="linkTarget.error"></p>
                </div>

                <x-slot name="footer">
                    <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                </x-slot>
            </x-dialog>
        @endif
    @endunless
</div>
