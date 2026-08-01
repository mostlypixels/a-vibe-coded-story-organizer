@props(['disabled' => false])

{{--
    The <select> twin of <x-text-input>. Callers supply their own layout classes
    (`mt-1 block w-full`, `text-sm`, …) and `$attributes->merge` appends them; only
    the border/focus/shape styling lives here, so a select cannot drift away from
    the inputs it sits next to in a form.

    The base string is deliberately repeated in text-input / select / textarea
    rather than hoisted into a shared @utility: three literal copies are greppable
    and obvious to a junior reader, and the theme-switcher spec rewrites all three
    in one pass anyway.
--}}
<select @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-xs']) }}>
    {{ $slot }}
</select>
