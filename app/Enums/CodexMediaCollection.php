<?php

namespace App\Enums;

enum CodexMediaCollection: string
{
    case Cover = 'cover';
    case ReferenceImage = 'reference_image';
    case ReferenceFile = 'reference_file';

    /**
     * Human-readable label for the collection.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cover => 'Cover',
            self::ReferenceImage => 'Reference Image',
            self::ReferenceFile => 'Reference File',
        };
    }
}
