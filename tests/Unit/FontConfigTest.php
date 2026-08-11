<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * `config/fonts.php` is authored data, so its invariants are guarded here the
 * way a model's `saving` hook would guard a database row.
 */
class FontConfigTest extends TestCase
{
    public function test_every_family_declares_the_full_shape(): void
    {
        foreach (config('fonts.families') as $slug => $family) {
            $this->assertArrayHasKey('name', $family, "Family [{$slug}] is missing name.");
            $this->assertArrayHasKey('stack', $family, "Family [{$slug}] is missing stack.");
            $this->assertArrayHasKey('bundled', $family, "Family [{$slug}] is missing bundled.");
            $this->assertArrayHasKey('note', $family, "Family [{$slug}] is missing note.");
            $this->assertIsBool($family['bundled'], "Family [{$slug}]'s bundled flag must be boolean.");
        }
    }

    public function test_every_default_points_at_a_key_of_its_own_list(): void
    {
        $this->assertArrayHasKey(config('fonts.default_ui'), config('fonts.families'));
        $this->assertArrayHasKey(config('fonts.default_manuscript'), config('fonts.families'));
        $this->assertArrayHasKey(config('fonts.default_ui_scale'), config('fonts.ui_scales'));
        $this->assertArrayHasKey(config('fonts.default_manuscript_scale'), config('fonts.manuscript_scales'));
        $this->assertArrayHasKey(config('fonts.default_leading'), config('fonts.leading'));
    }

    public function test_exactly_the_five_expected_families_are_bundled(): void
    {
        $bundled = array_keys(array_filter(
            config('fonts.families'),
            static fn (array $family): bool => $family['bundled'],
        ));

        $this->assertSame(
            ['inter', 'atkinson', 'lexend', 'literata', 'source-serif-4'],
            $bundled,
        );
    }

    /**
     * Every bundled family must have at least one matching `@font-face` block
     * in app.css, and every `@font-face` family name must be a bundled
     * family — the two-copies-cannot-drift guard, same shape as
     * ThemePresetTest's config-vs-CSS comparison.
     *
     * Compared against `stack`'s primary family, not `name`: `name` is the
     * picker's display label and may read differently (Atkinson's display
     * name drops the "Next" suffix its actual bundled font carries).
     */
    public function test_every_bundled_family_has_a_matching_font_face_block(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));

        preg_match_all("/font-family:\s*'([^']+)';/", $css, $matches);
        $cssFamilyNames = array_unique($matches[1]);

        $bundled = config('fonts.families');
        $bundledNames = array_map(
            static fn (array $family): string => trim(explode(',', $family['stack'])[0], " '\""),
            array_filter($bundled, static fn (array $family): bool => $family['bundled']),
        );

        foreach ($bundledNames as $slug => $name) {
            $this->assertContains(
                $name,
                $cssFamilyNames,
                "Bundled family [{$slug}] has no @font-face block in app.css.",
            );
        }

        foreach ($cssFamilyNames as $name) {
            $this->assertContains(
                $name,
                $bundledNames,
                "app.css has an @font-face block for [{$name}] that is not a bundled family in config/fonts.php.",
            );
        }
    }

    /**
     * `font-manuscript` reaches the two prose surfaces (rendered rich HTML and the
     * WYSIWYG editable area) but not the toolbar, which stays chrome. CSS-in-JS
     * (the Tiptap `editorProps.class` string) has no HTTP-rendered markup to
     * assert on, so this is a source grep, the same shape as the `@font-face`
     * drift check above.
     */
    public function test_prose_surfaces_carry_the_manuscript_face_and_the_toolbar_does_not(): void
    {
        $richText = file_get_contents(base_path('resources/views/components/rich-text.blade.php'));
        $this->assertStringContainsString('font-manuscript', $richText);

        $wysiwygJs = file_get_contents(base_path('resources/js/wysiwyg.js'));
        $this->assertMatchesRegularExpression(
            "/class:\s*'prose[^']*font-manuscript[^']*'/",
            $wysiwygJs,
            'The Tiptap editable-area class list must carry font-manuscript.',
        );

        $wysiwygBlade = file_get_contents(base_path('resources/views/components/wysiwyg.blade.php'));
        $this->assertStringNotContainsString('font-manuscript', $wysiwygBlade);
    }

    /**
     * A missing woff2 is invisible in dev (the browser falls through the
     * stack) and only surfaces in production as "the font setting does
     * nothing" — so every referenced file must actually exist on disk.
     */
    public function test_every_font_face_src_file_exists(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));

        preg_match_all("#url\('(/fonts/[^']+\.woff2)'\)#", $css, $matches);
        $srcPaths = array_unique($matches[1]);

        $this->assertNotEmpty($srcPaths, 'No @font-face src paths found in app.css.');

        foreach ($srcPaths as $path) {
            $this->assertFileExists(
                public_path(ltrim($path, '/')),
                "@font-face references [{$path}], which does not exist in public/fonts.",
            );
        }
    }
}
