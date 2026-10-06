<?php

namespace Tests\Unit;

use App\Enums\CodexEntryType;
use App\Models\CodexEntry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CodexEntryInitialsTest extends TestCase
{
    #[DataProvider('characterProvider')]
    public function test_a_character_gets_the_first_letter_of_the_first_and_last_word(string $name, ?string $expected): void
    {
        $entry = new CodexEntry(['type' => CodexEntryType::Character, 'name' => $name]);

        $this->assertSame($expected, $entry->initials());
    }

    public static function characterProvider(): array
    {
        return [
            'two words' => ['Jean Valjean', 'JV'],
            'one word' => ['Gavroche', 'G'],
            'three words' => ['Marius Pontmercy Baron', 'MB'],
            'accented lowercase' => ['éponine thénardier', 'ÉT'],
            'quote marks' => ['« Le Cabuc »', 'LC'],
            'extra spaces' => ['  Jean   Valjean  ', 'JV'],
            'no letters' => ['1832', null],
        ];
    }

    public function test_a_location_or_an_organization_has_no_initials(): void
    {
        $location = new CodexEntry(['type' => CodexEntryType::Location, 'name' => 'Rue Plumet']);
        $organization = new CodexEntry(['type' => CodexEntryType::Organization, 'name' => 'Patron-Minette']);

        $this->assertNull($location->initials());
        $this->assertNull($organization->initials());
    }
}
