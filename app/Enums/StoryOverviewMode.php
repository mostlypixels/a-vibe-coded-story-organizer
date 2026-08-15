<?php

namespace App\Enums;

/**
 * How the Story overview renders a project's chapters.
 *
 * `Chapter` paginates one chapter per page and stays fast on a long story.
 * `Book` renders every chapter's content in one page and stays as slow as
 * the story is long — see documentation/architecture.md → *Story overview*.
 */
enum StoryOverviewMode: string
{
    case Chapter = 'chapter';
    case Book = 'book';

    public function label(): string
    {
        return match ($this) {
            self::Chapter => 'One chapter per page',
            self::Book => 'Entire book',
        };
    }

    public static function default(): self
    {
        return self::Chapter;
    }
}
