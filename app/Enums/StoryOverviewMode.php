<?php

namespace App\Enums;

/**
 * How the Story overview renders a project's chapters.
 *
 * `Chapter` paginates one chapter per page and stays fast on a long story.
 * `Whole` renders every chapter's content in one page and stays as slow as
 * the story is long — see documentation/architecture/README.md → *Rendering and public access*.
 */
enum StoryOverviewMode: string
{
    case Chapter = 'chapter';
    case Whole = 'whole';

    public function label(): string
    {
        return match ($this) {
            self::Chapter => 'One chapter per page',
            self::Whole => 'Whole book',
        };
    }

    public static function default(): self
    {
        return self::Chapter;
    }
}
