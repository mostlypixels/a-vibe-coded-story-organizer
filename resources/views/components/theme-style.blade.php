{{-- The active preset's and font choice's :root overrides, so the whole app repaints
     without a rebuild. The markup lives only here and every layout includes it, for
     the same reason as x-robots-meta: two <style> tags per layout drift apart.

     NEVER wrap this block in @layer. An unlayered :root rule is what outranks
     Tailwind's `@layer theme`, at any source order — layering it would silently
     lose, and nothing would error.

     {!! !!} is deliberate: escaped CSS is not CSS. It is also exactly why
     ThemeStyleBlock whitelist-validates every value it renders, and why FontChoice
     only ever returns config-authored values. Do not copy the unescaped echo
     without one of those guarantees on the other side.

     Resolution: the authenticated user's stored slugs, falling back to config
     defaults when unset OR guest OR a stored slug no longer matches a configured
     entry. ThemePreset::resolve() and FontChoice::resolve() own all three cases
     each, so these lines stay one-liners regardless. --}}
<style>{!! app(\App\Services\ThemeStyleBlock::class)->render(
    \App\Support\ThemePreset::resolve(auth()->user()?->theme_slug)
) !!}{!! app(\App\Services\FontStyleBlock::class)->render(
    \App\Support\FontChoice::resolve(
        auth()->user()?->ui_font,
        auth()->user()?->manuscript_font,
        auth()->user()?->ui_scale,
        auth()->user()?->manuscript_scale,
        auth()->user()?->manuscript_leading,
    )
) !!}</style>
