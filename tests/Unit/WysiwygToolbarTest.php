<?php

namespace Tests\Unit;

use App\Support\RichTextFields;
use App\Support\WysiwygToolbar;
use Tests\TestCase;

/**
 * `alignment()` and `textColor()` are the two dropdowns decoration adds to the
 * toolbar. Markdown scene text stays structural, so both must go empty together
 * for it — the same boolean the slash menu (`resources/js/wysiwyg.js`) gates on.
 */
class WysiwygToolbarTest extends TestCase
{
    public function test_alignment_and_text_color_agree_on_the_markdown_gate(): void
    {
        $markdown = new WysiwygToolbar(markdown: true);
        $html = new WysiwygToolbar(markdown: false);

        // Assert the pair together: a test per method could pass with the two
        // dropdowns disagreeing on the gate, which is the bug this class exists
        // to catch.
        $this->assertSame([[], []], [$markdown->alignment(), $markdown->textColor()]);
        $this->assertNotEmpty($html->alignment());
        $this->assertNotEmpty($html->textColor());
    }

    public function test_alignment_has_one_item_per_registry_value_plus_a_left_reset(): void
    {
        $toolbar = new WysiwygToolbar(markdown: false);

        $aligns = array_column($toolbar->alignment(), 'args');
        $aligns = array_map(fn (array $args) => $args['align'], $aligns);

        $this->assertSame(['left', ...RichTextFields::ALIGNMENTS], $aligns);
    }

    public function test_text_color_has_one_item_per_registry_value_plus_a_remove_item(): void
    {
        $toolbar = new WysiwygToolbar(markdown: false);
        $items = $toolbar->textColor();

        $colors = array_map(fn (array $item) => $item['args']['color'] ?? null, $items);
        // The trailing item is the "remove colour" reset: no `color` arg, an
        // `unsetTextColor` command instead of `setTextColor`.
        $this->assertSame([...RichTextFields::TEXT_COLORS, null], $colors);
        $this->assertSame('unsetTextColor', end($items)['command']);
    }

    public function test_alignment_active_expression_covers_every_registry_value(): void
    {
        $toolbar = new WysiwygToolbar(markdown: false);
        $expression = $toolbar->alignmentActiveExpression();

        foreach (RichTextFields::ALIGNMENTS as $align) {
            $this->assertStringContainsString($align, $expression);
        }

        // `left` writes no class and is not a registry value; the trigger must
        // not highlight for the default alignment.
        $this->assertStringNotContainsString("'left'", $expression);
    }
}
