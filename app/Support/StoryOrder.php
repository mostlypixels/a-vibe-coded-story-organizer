<?php

namespace App\Support;

use App\Models\Scene;
use Illuminate\Database\Eloquent\Builder;

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
    /**
     * Order a joined query in story order, outermost level first.
     *
     * @param  list<string>  $tables  The joined tables, for example `['acts', 'chapters']`.
     */
    public static function orderQuery(Builder $query, array $tables, string $direction = 'asc'): Builder
    {
        foreach ($tables as $table) {
            $query->orderBy($table.'.position', $direction)->orderBy($table.'.id', $direction);
        }

        return $query;
    }

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
