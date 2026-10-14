<?php

namespace Tests\Unit\Support;

use App\Enums\LinkTargetType;
use App\Support\EntityLinks;
use PHPUnit\Framework\TestCase;

class EntityLinksTest extends TestCase
{
    /** @return list<string> */
    private function refs(string $text): array
    {
        return array_map(
            fn (array $link): string => $link['type']->value.'/'.$link['id'],
            EntityLinks::find($text),
        );
    }

    public function test_it_finds_several_html_links_in_text_order(): void
    {
        $html = '<p>See <a href="entity:scene/42">the ball</a> and <a href="entity:note/7">a note</a>.</p>'
            .'<p><a href="entity:codex/3">Javert</a> and <a href="entity:scene/42">again</a></p>';

        $this->assertSame(['scene/42', 'note/7', 'codex/3', 'scene/42'], $this->refs($html));
        $this->assertSame(LinkTargetType::Scene, EntityLinks::find($html)[0]['type']);
        $this->assertSame(42, EntityLinks::find($html)[0]['id']);
    }

    public function test_it_finds_several_markdown_links(): void
    {
        $markdown = "See [the ball](entity:scene/42) and [a note](entity:note/7).\n\n[Paris](entity:codex/3)";

        $this->assertSame(['scene/42', 'note/7', 'codex/3'], $this->refs($markdown));
    }

    public function test_it_finds_links_inside_and_around_marks(): void
    {
        $html = '<p><strong><a href="entity:chapter/5">bold</a></strong> <a href="entity:event/9"><em>it</em>alic</a></p>';
        $markdown = '**[bold](entity:chapter/5)** [*it*alic](entity:event/9) [a \] bracket](entity:book/1)';

        $this->assertSame(['chapter/5', 'event/9'], $this->refs($html));
        $this->assertSame(['chapter/5', 'event/9', 'book/1'], $this->refs($markdown));
    }

    public function test_entity_text_that_is_not_a_link_is_ignored(): void
    {
        $this->assertSame([], $this->refs('<p>Type entity:scene/42 to link.</p>'));
        $this->assertSame([], $this->refs('<p>&lt;a href="entity:scene/42"&gt;x&lt;/a&gt;</p>'));
        $this->assertSame([], $this->refs('Type entity:scene/42 to link. ![image](entity:scene/42)'));
    }

    public function test_unknown_types_and_malformed_targets_are_not_links(): void
    {
        $text = '<a href="entity:unknown/1">a</a> <a href="entity:scene/abc">b</a> <a href="entity:scene/1?x">c</a> [d](entity:planet/2)';

        $this->assertSame([], $this->refs($text));
        $this->assertSame($text, EntityLinks::strip($text));
    }

    public function test_rewrite_replaces_html_hrefs_and_keeps_web_links(): void
    {
        $html = '<p><a href="https://example.com">web</a> <a href="entity:scene/42"><em>ball</em></a></p>';

        $out = EntityLinks::rewrite($html, fn (LinkTargetType $type, int $id): string => EntityLinks::href($type, $id + 100));

        $this->assertSame('<p><a href="https://example.com">web</a> <a href="entity:scene/142"><em>ball</em></a></p>', $out);
    }

    public function test_rewrite_escapes_the_new_html_href(): void
    {
        $out = EntityLinks::rewrite('<a href="entity:scene/1">x</a>', fn (): string => '/a?b=1&c="2"');

        $this->assertSame('<a href="/a?b=1&amp;c=&quot;2&quot;">x</a>', $out);
    }

    public function test_rewrite_replaces_markdown_destinations_and_keeps_web_links(): void
    {
        $markdown = '[web](https://example.com) [*ball*](entity:scene/42) [x](entity:note/1)';

        $out = EntityLinks::rewrite($markdown, fn (LinkTargetType $type, int $id): string => $type === LinkTargetType::Note ? '/a (b)' : 'entity:scene/7');

        $this->assertSame('[web](https://example.com) [*ball*](entity:scene/7) [x](</a (b)>)', $out);
    }

    public function test_rewrite_with_null_unwraps_to_the_link_text(): void
    {
        $out = EntityLinks::rewrite(
            '<p><a href="entity:scene/1">kept</a> <a href="entity:scene/2"><strong>gone</strong></a></p> [md](entity:scene/2)',
            fn (LinkTargetType $type, int $id): ?string => $id === 1 ? 'entity:scene/10' : null,
        );

        $this->assertSame('<p><a href="entity:scene/10">kept</a> <strong>gone</strong></p> md', $out);
    }

    public function test_strip_unwraps_every_entity_link_in_both_formats(): void
    {
        $text = '<p><a href="https://example.com">web</a> <a href="entity:scene/1"><em>a</em></a></p> **[b](entity:note/2)**';

        $this->assertSame('<p><a href="https://example.com">web</a> <em>a</em></p> **b**', EntityLinks::strip($text));
    }

    public function test_strip_anchors_unwraps_html_links_and_leaves_markdown_shaped_text(): void
    {
        $html = '<p><a href="https://example.com">web</a> <a href="entity:scene/1"><em>a</em></a></p><pre>[b](entity:note/2)</pre>';

        $this->assertSame('<p><a href="https://example.com">web</a> <em>a</em></p><pre>[b](entity:note/2)</pre>', EntityLinks::stripAnchors($html));
    }

    public function test_parse_target_reads_only_known_type_and_digits(): void
    {
        $this->assertSame(['type' => LinkTargetType::Note, 'id' => 7], EntityLinks::parseTarget('note/7'));
        $this->assertNull(EntityLinks::parseTarget('unknown/1'));
        $this->assertNull(EntityLinks::parseTarget('scene/abc'));
        $this->assertNull(EntityLinks::parseTarget('scene/1/2'));
        $this->assertNull(EntityLinks::parseTarget('/scene/1'));
    }

    public function test_replace_anchors_replaces_html_links_only(): void
    {
        $html = '<p><a href="entity:scene/42" class="x"><em>ball</em></a> [md](entity:note/7) <a href="https://e.com">web</a></p>';

        $result = EntityLinks::replaceAnchors(
            $html,
            fn (LinkTargetType $type, int $id, string $anchor, string $text): string => "[{$type->value}/{$id}|{$anchor}|{$text}]",
        );

        $this->assertSame(
            '<p>[scene/42|<a href="entity:scene/42" class="x"><em>ball</em></a>|<em>ball</em>] [md](entity:note/7) <a href="https://e.com">web</a></p>',
            $result,
        );
    }
}
