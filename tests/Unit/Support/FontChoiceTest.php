<?php

namespace Tests\Unit\Support;

use App\Support\FontChoice;
use Tests\TestCase;

/**
 * FontChoice::resolve() is the only entry point from stored slugs to CSS —
 * every field must fall back the same way, whether it is `null` or a slug
 * that no longer exists in config.
 */
class FontChoiceTest extends TestCase
{
    public function test_null_fields_resolve_to_the_config_defaults(): void
    {
        $choice = FontChoice::resolve(null, null, null, null, null);

        $this->assertSame(config('fonts.default_ui'), $choice->uiSlug);
        $this->assertSame(config('fonts.families.'.config('fonts.default_ui').'.stack'), $choice->uiStack);

        $this->assertSame(config('fonts.default_manuscript'), $choice->manuscriptSlug);
        $this->assertSame(
            config('fonts.families.'.config('fonts.default_manuscript').'.stack'),
            $choice->manuscriptStack,
        );

        $this->assertSame(config('fonts.default_ui_scale'), $choice->uiScaleSlug);
        $this->assertSame(config('fonts.ui_scales.'.config('fonts.default_ui_scale')), $choice->uiScale);

        $this->assertSame(config('fonts.default_manuscript_scale'), $choice->manuscriptScaleSlug);
        $this->assertSame(
            config('fonts.manuscript_scales.'.config('fonts.default_manuscript_scale')),
            $choice->manuscriptScale,
        );

        $this->assertSame(config('fonts.default_leading'), $choice->leadingSlug);
        $this->assertSame(config('fonts.leading.'.config('fonts.default_leading')), $choice->leading);
    }

    public function test_a_slug_removed_from_config_falls_back_to_the_default_instead_of_throwing(): void
    {
        $choice = FontChoice::resolve('no-such-family', 'no-such-family', 'no-such-scale', 'no-such-scale', 'no-such-leading');

        $this->assertSame(config('fonts.default_ui'), $choice->uiSlug);
        $this->assertSame(config('fonts.default_manuscript'), $choice->manuscriptSlug);
        $this->assertSame(config('fonts.default_ui_scale'), $choice->uiScaleSlug);
        $this->assertSame(config('fonts.default_manuscript_scale'), $choice->manuscriptScaleSlug);
        $this->assertSame(config('fonts.default_leading'), $choice->leadingSlug);
    }

    public function test_a_valid_slug_resolves_to_its_own_authored_value_per_field(): void
    {
        $choice = FontChoice::resolve('atkinson', 'literata', 'large', 'larger', 'airy');

        $this->assertSame('atkinson', $choice->uiSlug);
        $this->assertSame(config('fonts.families.atkinson.stack'), $choice->uiStack);

        $this->assertSame('literata', $choice->manuscriptSlug);
        $this->assertSame(config('fonts.families.literata.stack'), $choice->manuscriptStack);

        $this->assertSame('large', $choice->uiScaleSlug);
        $this->assertSame(config('fonts.ui_scales.large'), $choice->uiScale);

        $this->assertSame('larger', $choice->manuscriptScaleSlug);
        $this->assertSame(config('fonts.manuscript_scales.larger'), $choice->manuscriptScale);

        $this->assertSame('airy', $choice->leadingSlug);
        $this->assertSame(config('fonts.leading.airy'), $choice->leading);
    }
}
