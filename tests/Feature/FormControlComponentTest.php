<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `<x-text-input>`, `<x-select>` and `<x-textarea>` — the three components that own
 * the app's form-control styling.
 *
 * Rendered standalone via `Blade::render()`, following {@see IconButtonComponentTest}'s
 * precedent, and for the same reason: 37 call sites used to re-type
 * `border-border-strong focus:border-focus focus:ring-focus rounded-md shadow-xs`
 * by hand. A page test that asserts a 200 cannot tell a styled control from an
 * unstyled one, and a control that silently loses `focus:ring-focus` is invisible
 * until someone tabs into it and no focus ring appears.
 *
 * What is load-bearing here:
 *
 *   - **The base string reaches the rendered element** on all three, and survives a
 *     call site adding its own layout classes (`$attributes->merge` must append, not
 *     replace).
 *   - **`<textarea>` content stays byte-exact.** Its content is literal: a stray
 *     newline introduced by "tidying" the component's markup becomes part of the
 *     submitted value, and leading whitespace in a saved draft is the kind of bug
 *     that survives review.
 */
class FormControlComponentTest extends TestCase
{
    /**
     * The styling every form control shares. Written out per class rather than as
     * one string because `$attributes->merge` controls the order, and order is
     * exactly what a test should not depend on.
     */
    private const BASE_CLASSES = [
        'bg-surface-raised',
        // A form control does not inherit the page's `color` — without this it keeps the
        // browser's near-black and a dark preset renders dark text on a dark box.
        'text-content',
        'border-border-strong',
        'focus:border-focus',
        'focus:ring-focus',
        'rounded-md',
        'shadow-xs',
    ];

    /**
     * Render a template, asserting the component tag actually compiled.
     *
     * An unmatched `<x-…>` tag is not an error — Blade leaves it on the page as
     * literal text, and every "does the output contain …" assertion below would
     * then pass against unrendered source. Asserting the tag is gone is what makes
     * the rest mean anything.
     */
    private function render(string $template, array $data = []): string
    {
        $rendered = Blade::render($template, $data);

        $this->assertStringNotContainsString(
            '<x-',
            $rendered,
            'A component tag was emitted as literal text instead of being compiled.',
        );

        return $rendered;
    }

    private function assertCarriesBaseClasses(string $rendered): void
    {
        foreach (self::BASE_CLASSES as $class) {
            $this->assertStringContainsString($class, $rendered);
        }
    }

    public function test_the_text_input_carries_the_base_classes(): void
    {
        $rendered = $this->render('<x-text-input name="name" type="text" />');

        $this->assertStringContainsString('<input', $rendered);
        $this->assertCarriesBaseClasses($rendered);
    }

    public function test_the_select_carries_the_base_classes_and_renders_its_options(): void
    {
        $rendered = $this->render('<x-select name="act_id"><option value="1">First act</option></x-select>');

        $this->assertStringContainsString('<select', $rendered);
        $this->assertStringContainsString('<option value="1">First act</option>', $rendered);
        $this->assertCarriesBaseClasses($rendered);
    }

    public function test_the_textarea_carries_the_base_classes(): void
    {
        $rendered = $this->render('<x-textarea name="rights" rows="5" />');

        $this->assertStringContainsString('<textarea', $rendered);
        $this->assertStringContainsString('rows="5"', $rendered);
        $this->assertCarriesBaseClasses($rendered);
    }

    /**
     * The call site owns layout; the component owns styling. If merge ever replaced
     * instead of appending, every control in the app would lose its width or its
     * border — depending which side won.
     */
    public function test_call_site_classes_are_added_to_the_base_string_not_swapped_for_it(): void
    {
        foreach (['<x-text-input name="a" class="mt-1 block w-full" />',
            '<x-select name="a" class="mt-1 block w-full" />',
            '<x-textarea name="a" class="mt-1 block w-full" />'] as $template) {
            $rendered = $this->render($template);

            $this->assertCarriesBaseClasses($rendered);
            $this->assertStringContainsString('mt-1', $rendered);
            $this->assertStringContainsString('block', $rendered);
            $this->assertStringContainsString('w-full', $rendered);
        }
    }

    public function test_the_textarea_does_not_pad_its_value_with_whitespace(): void
    {
        $rendered = $this->render('<x-textarea name="rights">{{ $value }}</x-textarea>', ['value' => 'All rights reserved.']);

        // Not merely "contains" — the value must sit flush against both tags, or the
        // padding becomes part of what the form submits.
        $this->assertStringContainsString('>All rights reserved.</textarea>', $rendered);
    }

    public function test_each_control_can_be_disabled(): void
    {
        foreach (['<x-text-input name="a" :disabled="true" />',
            '<x-select name="a" :disabled="true" />',
            '<x-textarea name="a" :disabled="true" />'] as $template) {
            $this->assertStringContainsString('disabled', $this->render($template));
        }
    }
}
