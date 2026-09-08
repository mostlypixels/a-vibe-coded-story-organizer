<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * The page holding the first row of a Go-to target's group.
 *
 * One static, no state, beside {@see PageSize} — this is arithmetic over a
 * query, not a workflow. It does not resolve or validate the target: the
 * caller (the `JumpsToListPosition` concern) already checked the id is one
 * of the book's own groups.
 */
final class ListJump
{
    /**
     * @param  Builder  $rows  The book's unfiltered row query.
     * @param  string  $column  'scenes.chapter_id' | 'chapters.act_id'.
     * @param  list<int>  $orderedIds  Group ids in story order.
     *
     * > [!WARNING]
     * > Correct only while $orderedIds matches the index's own `orderBy`
     * > chain exactly, `id` tie-breaks included. A dropped tie-break drifts
     * > the page silently.
     */
    public static function page(
        Builder $rows,
        string $column,
        array $orderedIds,
        int $targetId,
        int $perPage,
    ): int {
        $index = array_search($targetId, $orderedIds, true);

        if ($index === false) {
            throw new InvalidArgumentException("Target id {$targetId} is not in the ordered id list.");
        }

        $before = $rows->whereIn($column, array_slice($orderedIds, 0, $index))->count();

        return intdiv($before, $perPage) + 1;
    }
}
