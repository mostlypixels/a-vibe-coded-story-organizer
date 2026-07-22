@props(['command', 'args' => null, 'active' => null, 'label', 'title'])

{{--
    Shared toolbar button for the plain "cmd(command[, args])" + optional
    "isOn(active[, args])" shape used by most WYSIWYG toolbar buttons (headings,
    bold/italic/underline/strike, lists/blocks, table row/column ops, merge/split).

    Buttons that call a bespoke no-arg helper instead of cmd() — Link (setLink()),
    Image (setImage()), Callout (toggleCallout()) — don't fit this shape and stay
    hand-written in wysiwyg.blade.php; see ../../.specs .../expanded/ui.md.
--}}
<button
    type="button"
    @click="cmd({{ Illuminate\Support\Js::from($command) }}{{ $args ? ', '.Illuminate\Support\Js::from($args) : '' }})"
    @if($active)
        :class="isOn({{ Illuminate\Support\Js::from($active[0]) }}{{ isset($active[1]) ? ', '.Illuminate\Support\Js::from($active[1]) : '' }}) ? 'bg-ocean-100 text-ocean-800' : 'text-gray-600 hover:bg-gray-200'"
    @else
        class="text-gray-600 hover:bg-gray-200"
    @endif
    {{ $attributes->merge(['class' => 'inline-flex min-w-[2rem] items-center justify-center rounded px-2 py-1 text-sm font-medium']) }}
    title="{{ $title }}"
    aria-label="{{ $title }}"
>{!! $label !!}</button>
