<?php

namespace App\Services;

use App\Support\FontChoice;

/**
 * Renders a resolved {@see FontChoice} as one unlayered `:root` rule, appended to the
 * theme block in the same `<style>` tag every layout includes.
 *
 * ## A stronger guarantee than ThemeStyleBlock
 *
 * `ThemeStyleBlock` whitelist-validates every value because a preset stores raw color
 * strings. Nothing here is user input at all: `FontChoice::resolve()` only ever returns
 * a `stack`/scale/leading value pulled from `config/fonts.php` by an authored key. A
 * slug that does not index the config falls back before it reaches this class, so there
 * is nothing to sanitize here.
 *
 * > [!WARNING]
 * > Keep the rule unlayered and starting `:root{`. Wrapping it in `@layer` loses to
 * > Tailwind's compiled `@layer theme` silently — nothing errors, it just stops working.
 */
final class FontStyleBlock
{
    public function render(FontChoice $choice): string
    {
        $declarations = sprintf(
            '--font-sans:%s;--font-manuscript:%s;--manuscript-leading:%s;--manuscript-scale:%s;font-size:%s;',
            $choice->uiStack,
            $choice->manuscriptStack,
            $choice->leading,
            $choice->manuscriptScale,
            $choice->uiScale,
        );

        return ":root{{$declarations}}";
    }
}
