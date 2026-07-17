<?php

namespace App\Enums;

/**
 * How deep the table of contents navigates the book structure.
 * Drives both the in-book toc.xhtml page and the EPUB 3 nav document depth.
 * Scenes requires scene-level anchors.
 */
enum TableOfContentsDepth: string
{
    case Acts = 'acts';
    case Chapters = 'chapters';
    case Scenes = 'scenes';

    public function label(): string
    {
        return match ($this) {
            self::Acts => 'Acts only',
            self::Chapters => 'Acts and chapters',
            self::Scenes => 'Acts, chapters, and scenes',
        };
    }
}
