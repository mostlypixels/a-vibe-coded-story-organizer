{{-- The active preset's :root overrides, so the whole app repaints without a
     rebuild. The markup lives only here and every layout includes it, for the same
     reason as x-robots-meta: four copies of a <style> tag drift apart.

     NEVER wrap this block in @layer. An unlayered :root rule is what outranks
     Tailwind's `@layer theme`, at any source order — layering it would silently
     lose, and nothing would error.

     {!! !!} is deliberate: escaped CSS is not CSS. It is also exactly why
     ThemeStyleBlock whitelist-validates every value it renders. Do not copy the
     unescaped echo without that guarantee on the other side.

     Resolution: the authenticated user's stored theme_slug, falling back to
     config('themes.default') when unset OR guest OR the stored slug no longer
     matches a configured preset. ThemePreset::resolve() owns all three cases,
     so this line stays a one-liner regardless. --}}
<style>{!! app(\App\Services\ThemeStyleBlock::class)->render(
    \App\Support\ThemePreset::resolve(auth()->user()?->theme_slug)
) !!}</style>
