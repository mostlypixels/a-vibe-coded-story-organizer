<?php

namespace Tests\Unit;

use App\Services\SceneTextMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Pure PHP, no database: documents the matching contract find and replace relies on.
 */
class SceneTextMatcherTest extends TestCase
{
    private function matchCount(string $text, string $find, bool $matchCase = true, bool $wholeWord = true): int
    {
        return count((new SceneTextMatcher)->matches($text, $find, $matchCase, $wholeWord));
    }

    public function test_match_case_on_skips_other_casing(): void
    {
        $this->assertSame(0, $this->matchCount('Hello miss lane.', 'miss Lane', matchCase: true));
    }

    public function test_match_case_off_finds_other_casing(): void
    {
        $this->assertSame(1, $this->matchCount('Hello miss lane.', 'miss Lane', matchCase: false));
    }

    public function test_whole_word_skips_longer_words(): void
    {
        $this->assertSame(0, $this->matchCount('The Laneway is long.', 'Lane'));
    }

    public function test_whole_word_accepts_apostrophe_and_markdown_edges(): void
    {
        $this->assertSame(1, $this->matchCount("Lane's hat", 'Lane'));
        $this->assertSame(1, $this->matchCount('miss *Lane* said', 'Lane'));
    }

    public function test_partial_matches_are_found_when_whole_word_is_off(): void
    {
        $this->assertSame(1, $this->matchCount('The Laneway is long.', 'Lane', wholeWord: false));
    }

    public function test_it_is_accent_sensitive(): void
    {
        $this->assertSame(0, $this->matchCount('Hélène walked.', 'Helene', matchCase: false));
        $this->assertSame(1, $this->matchCount('Hélène walked.', 'Hélène'));
    }

    public function test_accented_letter_at_a_word_edge_counts_as_a_letter(): void
    {
        $this->assertSame(0, $this->matchCount('Les Hélènes sont là.', 'Hélène'));
        $this->assertSame(1, $this->matchCount('Les Hélènes sont là.', 'Hélène', wholeWord: false));
    }

    public function test_special_characters_are_literal(): void
    {
        $text = 'Wait... (really) a*b';

        $this->assertSame(1, $this->matchCount($text, '...', wholeWord: false));
        $this->assertSame(0, $this->matchCount('Wait, no.', '...', wholeWord: false));
        $this->assertSame(1, $this->matchCount($text, '(really)', wholeWord: false));
        $this->assertSame(1, $this->matchCount($text, 'a*b', wholeWord: false));
    }

    public function test_offsets_are_byte_offsets_usable_with_substr_replace(): void
    {
        $text = 'Élodie et Élodie';
        $matches = (new SceneTextMatcher)->matches($text, 'Élodie', true, true);

        $this->assertSame([['offset' => 0, 'length' => 7], ['offset' => 11, 'length' => 7]], $matches);
        $this->assertSame('X et X', substr_replace(substr_replace($text, 'X', 11, 7), 'X', 0, 7));
    }

    public function test_matches_do_not_overlap(): void
    {
        $this->assertSame(2, $this->matchCount('aaaa', 'aa', wholeWord: false));
    }

    public function test_empty_find_gives_no_matches(): void
    {
        $this->assertSame([], (new SceneTextMatcher)->matches('text', '', true, true));
    }

    public function test_invalid_utf8_gives_no_matches(): void
    {
        $this->assertSame([], (new SceneTextMatcher)->matches("bad \xFF bytes", 'bad', true, false));
        $this->assertSame([], (new SceneTextMatcher)->matches('text', "\xFF", true, false));
    }
}
