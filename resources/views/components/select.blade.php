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

    `bg-surface-raised` and `text-content` are new here — see <x-text-input> for why.
--}}
<select @disabled($disabled) {{ $attributes->merge(['class' => 'bg-surface-raised text-content border-border-strong focus:border-focus focus:ring-focus rounded-md shadow-xs']) }}>
    {{ $slot }}
</select>
