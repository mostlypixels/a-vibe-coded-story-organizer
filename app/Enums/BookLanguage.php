<?php

namespace App\Enums;

/**
 * The languages a Project's epub metadata (dc:language) may declare. A closed list rather
 * than free-text BCP-47 input, so every generated epub's language tag is one this app has
 * actually been proofed against. Add a case here (and its label) when support for another
 * language is added — do not widen `language` back to free text.
 */
enum BookLanguage: string
{
    case English = 'en';
    case French = 'fr';
    case Spanish = 'es';
    case Portuguese = 'pt';
    case Italian = 'it';
    case German = 'de';
    case Dutch = 'nl';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::French => 'French',
            self::Spanish => 'Spanish',
            self::Portuguese => 'Portuguese',
            self::Italian => 'Italian',
            self::German => 'German',
            self::Dutch => 'Dutch',
        };
    }
}
