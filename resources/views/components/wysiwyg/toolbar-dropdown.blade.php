@props([
    // One cluster from App\Support\WysiwygToolbar (styles(), typography(),
    // lists(), callouts(), code(), table()) — each entry the shape
    // x-wysiwyg.toolbar-button consumes (command/args or action, active, icon,
    // label, title).
    'items',
    // Tabler component name (without prefix) for the collapsed trigger glyph.
    'triggerIcon',
    // Tooltip / aria-label for the trigger.
    'title',
    // Raw JS boolean expression that highlights the trigger when any item in
    // the cluster is active. Optional: the Table dropdown has no active state.
    'activeExpression' => null,
])

{{-- The one definition behind every collapsed toolbar cluster. Was six
     copy-pasted <x-dropdown> blocks differing only in trigger icon/title and
     which WysiwygToolbar array fed the @foreach. --}}
<x-dropdown align="left" width="auto" contentClasses="p-1 bg-surface-overlay flex flex-col items-start gap-0.5">
    <x-slot name="trigger">
        <x-wysiwyg.toolbar-button
            :active-expression="$activeExpression"
            :title="$title"
            :dropdown="true"
        ><x-dynamic-component :component="'tabler-'.$triggerIcon" class="h-4 w-4" /></x-wysiwyg.toolbar-button>
    </x-slot>

    <x-slot name="content">
        @foreach ($items as $item)
            <x-wysiwyg.toolbar-button
                :command="$item['command'] ?? null"
                :args="$item['args'] ?? null"
                :action="$item['action'] ?? null"
                :active="$item['active'] ?? null"
                :title="$item['title']"
            >
                <span class="inline-flex items-center gap-2">
                    <x-dynamic-component :component="'tabler-'.$item['icon']" class="h-4 w-4 shrink-0" />
                    {{ $item['label'] }}
                </span>
            </x-wysiwyg.toolbar-button>
        @endforeach
    </x-slot>
</x-dropdown>
