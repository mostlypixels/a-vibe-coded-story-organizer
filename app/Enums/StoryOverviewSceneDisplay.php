<?php

namespace App\Enums;

/** What the Story overview shows under each scene name. */
enum StoryOverviewSceneDisplay: string
{
    case Description = 'description';
    case Content = 'content';

    public function label(): string
    {
        return match ($this) {
            self::Description => 'Scene description',
            self::Content => 'Scene content',
        };
    }

    public static function default(): self
    {
        return self::Description;
    }
}
