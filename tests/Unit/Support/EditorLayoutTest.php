<?php

namespace Tests\Unit\Support;

use App\Support\EditorLayout;
use Tests\TestCase;

class EditorLayoutTest extends TestCase
{
    public function test_null_resolves_to_the_configured_defaults(): void
    {
        $this->assertSame(config('editor.layout'), EditorLayout::resolve(null));
    }

    public function test_a_partial_value_keeps_its_keys_and_fills_the_rest(): void
    {
        $layout = EditorLayout::resolve([
            'left' => ['width' => 300],
            'sections' => ['codex' => true],
        ]);

        $this->assertSame(['width' => 300, 'collapsed' => false], $layout['left']);
        $this->assertSame(config('editor.layout.right'), $layout['right']);
        $this->assertSame(['codex' => true, 'notes' => false], $layout['sections']);
    }

    public function test_out_of_range_widths_are_clamped(): void
    {
        $layout = EditorLayout::resolve([
            'left' => ['width' => 50],
            'right' => ['width' => 5000],
        ]);

        $this->assertSame(200, $layout['left']['width']);
        $this->assertSame(640, $layout['right']['width']);
    }

    public function test_unknown_keys_and_wrong_types_are_dropped(): void
    {
        $layout = EditorLayout::resolve([
            'left' => ['width' => 'wide', 'collapsed' => 'yes', 'evil' => 1],
            'right' => 'nope',
            'sections' => ['codex' => 1, 'journal' => true],
            'extra' => true,
        ]);

        $this->assertSame(config('editor.layout'), $layout);
    }
}
