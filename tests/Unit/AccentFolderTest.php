<?php

namespace Tests\Unit;

use App\Support\AccentFolder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the accent-folding helper that makes search accent-insensitive.
 * Pure PHP (no DB), so it documents the folding contract both the SQL and PHP
 * sides of ProjectSearch depend on.
 */
class AccentFolderTest extends TestCase
{
    public function test_it_folds_accented_letters_to_their_plain_base(): void
    {
        $this->assertSame('melusine', AccentFolder::fold('Mélusine'));
        $this->assertSame('melusine', AccentFolder::fold('Melusine'));
    }

    public function test_it_lowercases_while_folding(): void
    {
        $this->assertSame('eeee', AccentFolder::fold('ÉÈÊË'));
        $this->assertSame('garcon', AccentFolder::fold('Garçon'));
    }

    public function test_it_covers_the_western_european_accent_set(): void
    {
        $this->assertSame('aaaaaa', AccentFolder::fold('àáâäãå'));
        $this->assertSame('ooooo', AccentFolder::fold('òóôöõ'));
        $this->assertSame('uuuu', AccentFolder::fold('ùúûü'));
        $this->assertSame('n', AccentFolder::fold('ñ'));
    }

    public function test_unmapped_characters_pass_through_unchanged(): void
    {
        // Digits, punctuation and spaces are left alone (only lowercased for ASCII).
        $this->assertSame('a-b_1 2', AccentFolder::fold('A-B_1 2'));
    }

    public function test_folding_preserves_character_length(): void
    {
        // The 1:1 invariant SearchSnippet's offset mapping depends on.
        $original = 'Crème brûlée';
        $this->assertSame(mb_strlen($original), mb_strlen(AccentFolder::fold($original)));
    }
}
