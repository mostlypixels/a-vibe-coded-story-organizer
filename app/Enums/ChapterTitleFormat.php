<?php

namespace App\Enums;

/**
 * How chapter headings are formatted in the EPUB, and their corresponding
 * TOC/nav labels. This is the single source of truth for both rendering
 * contexts.
 */
enum ChapterTitleFormat: string
{
    case ChapterNumberTitle = 'chapter_number_title';
    case NumberTitle = 'number_title';
    case ChapterNumber = 'chapter_number';
    case Number = 'number';
    case Title = 'title';

    public function label(): string
    {
        return match ($this) {
            self::ChapterNumberTitle => 'Chapter 12: The Storm',
            self::NumberTitle => '12: The Storm',
            self::ChapterNumber => 'Chapter 12',
            self::Number => '12',
            self::Title => 'The Storm',
        };
    }

    /**
     * Format a chapter heading and TOC/nav label.
     *
     * Guards against dangling ": " when name is empty or blank.
     *
     * @param  int  $number  The chapter's book-wide, gap-free display number (e.g. 12) —
     *                       see App\Support\StoryNumbering. NOT the chapter's `position`
     *                       column, which is a per-act, gappy sort key.
     * @param  ?string  $name  The chapter's name (e.g. "The Storm"), or null/empty
     * @return string The formatted heading
     */
    public function format(int $number, ?string $name): string
    {
        $trimmedName = trim($name ?? '');

        return match ($this) {
            self::ChapterNumberTitle => $trimmedName
                ? "Chapter {$number}: {$trimmedName}"
                : "Chapter {$number}",
            self::NumberTitle => $trimmedName
                ? "{$number}: {$trimmedName}"
                : (string) $number,
            self::ChapterNumber => "Chapter {$number}",
            self::Number => (string) $number,
            self::Title => $trimmedName ?: '',
        };
    }
}
