<?php

namespace App\Support;

use App\Models\Scene;

/**
 * The sort key for scenes that are already in memory, in story order.
 *
 * Story order is book, act, chapter, then scene. Each level sorts by
 * `position`, then `id`, because `position` is not unique. SQL callers use
 * the same keys in their `orderBy` chain.
 *
 * > [!WARNING]
 * > Eager-load `chapter.act.book` first, or each scene fires three queries.
 */
final class StoryOrder
{
    /** @return list<int> */
    public static function sceneKey(Scene $scene): array
    {
        $chapter = $scene->chapter;
        $act = $chapter->act;
        $book = $act->book;

        return [
            $book->position, $book->id,
            $act->position, $act->id,
            $chapter->position, $chapter->id,
            $scene->position, $scene->id,
        ];
    }
}
