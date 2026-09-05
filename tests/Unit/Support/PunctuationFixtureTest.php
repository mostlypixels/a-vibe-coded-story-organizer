<?php

namespace Tests\Unit\Support;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Checks `tests/Fixtures/punctuation.json` against SmartPunct itself.
 *
 * SmartPunct is the oracle for "canonical punctuation", not the shipped
 * implementation. `league/commonmark` has no Markdown-to-Markdown writer, so import needs its
 * own transform; this test is what keeps that transform honest against the
 * definition SmartPunct already embodies.
 *
 * > [!NOTE]
 * > `rock 'n' roll` stays wrong: SmartPunct reads both apostrophes as quote
 * > marks, not elisions. The fixture keeps that answer so every
 * > implementation agrees, rather than fixing it in only one place.
 */
class PunctuationFixtureTest extends TestCase
{
    #[DataProvider('fixtureCases')]
    public function test_smartpunct_matches_the_fixture(string $input, string $expected): void
    {
        $html = (string) $this->converter()->convert($input);

        $text = trim(preg_replace('/^<p>|<\/p>\s*$/', '', $html));

        $this->assertSame($expected, $text);
    }

    public static function fixtureCases(): array
    {
        $cases = json_decode(
            file_get_contents(__DIR__.'/../../Fixtures/punctuation.json'),
            true,
        );

        $named = [];
        foreach ($cases as $case) {
            $named[$case['input']] = [$case['input'], $case['expected']];
        }

        return $named;
    }

    private function converter(): CommonMarkConverter
    {
        $converter = new CommonMarkConverter;
        $converter->getEnvironment()->addExtension(new SmartPunctExtension);

        return $converter;
    }
}
