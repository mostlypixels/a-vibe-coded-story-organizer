<?php

namespace Tests\Unit;

use App\Support\NoteOutline;
use PHPUnit\Framework\TestCase;

class NoteOutlineTest extends TestCase
{
    public function test_it_lists_h1_to_h4_with_levels_and_anchors(): void
    {
        $outline = NoteOutline::from('<h1>One</h1><p>x</p><h2>Two <em>b</em></h2><h3>Three</h3><h4>Four</h4><h5>Skipped</h5>');

        $this->assertSame([
            ['level' => 1, 'text' => 'One', 'anchor' => 'one'],
            ['level' => 2, 'text' => 'Two b', 'anchor' => 'two-b'],
            ['level' => 3, 'text' => 'Three', 'anchor' => 'three'],
            ['level' => 4, 'text' => 'Four', 'anchor' => 'four'],
        ], $outline->headings);
        $this->assertStringContainsString('<h2 id="two-b">Two <em>b</em></h2>', $outline->html);
        $this->assertStringContainsString('<h5>Skipped</h5>', $outline->html);
    }

    public function test_repeated_text_gets_unique_anchors(): void
    {
        $outline = NoteOutline::from('<h2>Plan</h2><h2>Plan</h2><h2>Plan</h2><h2>Plan 2</h2>');

        $this->assertSame(['plan', 'plan-2', 'plan-3', 'plan-2-2'], array_column($outline->headings, 'anchor'));
        $this->assertSame(4, substr_count($outline->html, ' id="'));
    }

    public function test_a_body_without_headings_comes_back_unchanged(): void
    {
        $html = '<p>Just text.</p>';
        $outline = NoteOutline::from($html);

        $this->assertSame([], $outline->headings);
        $this->assertSame($html, $outline->html);
        $this->assertFalse($outline->hasContents());
        $this->assertSame('', NoteOutline::from(null)->html);
    }

    public function test_unicode_text_keeps_its_letters_in_the_anchor(): void
    {
        $outline = NoteOutline::from('<h2>Café Étoilé</h2><h2>日本語</h2><h2>!!!</h2>');

        $this->assertSame(['café-étoilé', '日本語', 'section'], array_column($outline->headings, 'anchor'));
    }

    public function test_entities_are_decoded_in_the_text_and_markup_is_kept(): void
    {
        $outline = NoteOutline::from('<h2>Tom &amp; Jerry</h2>');

        $this->assertSame('Tom & Jerry', $outline->headings[0]['text']);
        $this->assertSame('tom-jerry', $outline->headings[0]['anchor']);
        $this->assertStringContainsString('Tom &amp; Jerry', $outline->html);
    }

    public function test_an_existing_id_is_replaced(): void
    {
        $outline = NoteOutline::from('<h2 id="evil">Safe</h2>');

        $this->assertSame('<h2 id="safe">Safe</h2>', $outline->html);
    }

    public function test_contents_needs_three_headings(): void
    {
        $this->assertFalse(NoteOutline::from('<h2>A</h2><h2>B</h2>')->hasContents());
        $this->assertTrue(NoteOutline::from('<h2>A</h2><h2>B</h2><h3>C</h3>')->hasContents());
        $this->assertSame(2, NoteOutline::from('<h2>A</h2><h3>B</h3><h3>C</h3>')->topLevel());
    }
}
