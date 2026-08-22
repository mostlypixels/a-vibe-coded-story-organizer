<?php

namespace App\Enums;

enum Genre: string
{
    case Contemporary = 'contemporary';
    case Historical = 'historical';
    case Fantasy = 'fantasy';
    case ScienceFiction = 'science_fiction';
    case Blank = 'blank';

    /**
     * Human-readable label for the genre picker.
     */
    public function label(): string
    {
        return match ($this) {
            self::Contemporary => 'Contemporary',
            self::Historical => 'Historical',
            self::Fantasy => 'Fantasy',
            self::ScienceFiction => 'Science Fiction',
            self::Blank => 'Blank',
        };
    }

    /**
     * One-line description for the genre picker.
     */
    public function description(): string
    {
        return match ($this) {
            self::Contemporary => 'Present-day settings and everyday stakes.',
            self::Historical => 'A real past era, researched and grounded.',
            self::Fantasy => 'Invented worlds, magic, and myth.',
            self::ScienceFiction => 'Future tech, space, and speculative science.',
            self::Blank => 'Something else. Start blank and build it yourself.',
        };
    }
}
