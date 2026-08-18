<style>{!! app(\App\Services\ThemeStyleBlock::class)->render(
    \App\Support\ThemePreset::resolve(auth()->user()?->theme_slug)
) !!}{!! app(\App\Services\FontStyleBlock::class)->render(
    \App\Support\FontChoice::resolve(
        auth()->user()?->ui_font,
        auth()->user()?->manuscript_font,
        auth()->user()?->ui_scale,
        auth()->user()?->manuscript_scale,
        auth()->user()?->manuscript_leading,
        auth()->user()?->ui_leading,
    )
) !!}</style>
