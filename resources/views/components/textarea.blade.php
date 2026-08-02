@props(['disabled' => false])

{{--
    The <textarea> twin of <x-text-input>. See <x-select> for why the base class
    string is repeated across the three form controls rather than shared, and
    <x-text-input> for why `bg-surface-raised` and `text-content` are new here too.
--}}

{{--
    > [!WARNING]
    > A <textarea>'s content is literal: every space and newline between the tags
    > is part of the value. The opening tag therefore ends and the closing tag
    > begins hard against `$slot`, with no formatting whitespace — do not "tidy"
    > this onto separate lines.
--}}
<textarea @disabled($disabled) {{ $attributes->merge(['class' => 'bg-surface-raised text-content placeholder:text-content-subtle border-border-strong focus:border-focus focus:ring-focus rounded-md shadow-xs']) }}>{{ $slot }}</textarea>
