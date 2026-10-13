<?php

namespace Tests\Unit\Support;

use App\Services\ThemeStyleBlock;
use App\Support\AppearancePreviewMap;
use App\Support\FontChoice;
use App\Support\PageWidth;
use App\Support\ThemePreset;
use Tests\TestCase;

class AppearancePreviewMapTest extends TestCase
{
    public function test_the_map_holds_one_entry_per_appearance_field(): void
    {
        $map = AppearancePreviewMap::build(app(ThemeStyleBlock::class));

        $this->assertSame([
            'theme_slug',
            'ui_font',
            'manuscript_font',
            'ui_scale',
            'manuscript_scale',
            'manuscript_leading',
            'ui_leading',
            'page_width',
        ], array_keys($map));
    }

    public function test_theme_entries_equal_the_renderer_declarations(): void
    {
        $themeStyle = app(ThemeStyleBlock::class);
        $map = AppearancePreviewMap::build($themeStyle);

        foreach (ThemePreset::all() as $slug => $preset) {
            $this->assertSame($themeStyle->declarations($preset), $map['theme_slug'][$slug]);
        }
    }

    public function test_font_entries_equal_the_config_stacks(): void
    {
        $map = AppearancePreviewMap::build(app(ThemeStyleBlock::class));

        foreach (config('fonts.families') as $slug => $family) {
            $this->assertSame($family['stack'], $map['ui_font'][$slug]);
            $this->assertSame($family['stack'], $map['manuscript_font'][$slug]);
        }
    }

    public function test_scale_and_spacing_entries_come_from_config_and_font_choice(): void
    {
        $map = AppearancePreviewMap::build(app(ThemeStyleBlock::class));

        $this->assertSame(config('fonts.ui_scales'), $map['ui_scale']);
        $this->assertSame(config('fonts.manuscript_scales'), $map['manuscript_scale']);
        $this->assertSame(FontChoice::lineHeightsFor('ui'), $map['ui_leading']);
        $this->assertSame(FontChoice::lineHeightsFor('manuscript'), $map['manuscript_leading']);
    }

    public function test_page_width_is_keyed_by_every_step_with_its_css_value(): void
    {
        $map = AppearancePreviewMap::build(app(ThemeStyleBlock::class));

        $this->assertSame(array_keys($map['page_width']), PageWidth::steps());

        foreach (PageWidth::steps() as $step) {
            $this->assertSame(PageWidth::css($step), $map['page_width'][$step]);
        }
    }
}
