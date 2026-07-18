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

    /**
     * Whether the table of contents / nav descends to individual chapters. False only for
     * {@see self::Acts}, where the navigation lists acts alone. The exporter asks the enum
     * rather than matching on the raw case, keeping the depth logic in one place.
     */
    public function includesChapters(): bool
    {
        return $this !== self::Acts;
    }

    /**
     * Whether the table of contents / nav descends all the way to individual scenes (a
     * third nav level of in-page anchor links). True only for {@see self::Scenes}; this is
     * also what tells the chapter view to emit per-scene `id` anchors for those links to
     * resolve against.
     */
    public function includesScenes(): bool
    {
        return $this === self::Scenes;
    }
}
