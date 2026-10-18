<?php

namespace App\Support;

use App\Models\Scene;
use Illuminate\Database\Eloquent\Builder;

/**
 * The scenes just before and just after one scene, in story order inside its book.
 *
 * Each side is one `limit 1` query, so a long book costs no more than a short one.
 * The order crosses chapter and act limits; scenes of other books never appear.
 */
final class SceneNeighbours
{
    /** @return array{0: ?Scene, 1: ?Scene} The previous scene, then the next one. */
    public static function of(Scene $scene): array
    {
        return [self::find($scene, 'desc'), self::find($scene, 'asc')];
    }

    private static function find(Scene $scene, string $direction): ?Scene
    {
        $chapter = $scene->chapter;
        $act = $chapter->act;

        $keys = [
            'acts.position' => $act->position,
            'acts.id' => $act->id,
            'chapters.position' => $chapter->position,
            'chapters.id' => $chapter->id,
            'scenes.position' => $scene->position,
            'scenes.id' => $scene->id,
        ];

        $query = $act->book->sceneQuery()
            ->select('scenes.*')
            ->join('chapters', 'chapters.id', '=', 'scenes.chapter_id')
            ->join('acts', 'acts.id', '=', 'chapters.act_id')
            ->where(fn (Builder $query) => self::whereBeyond($query, $keys, $direction === 'asc' ? '>' : '<'));

        return StoryOrder::orderQuery($query, ['acts', 'chapters', 'scenes'], $direction)->first();
    }

    /**
     * The row-value comparison `(k1, k2, …) > (v1, v2, …)`, as plain where clauses.
     *
     * A row is beyond the scene when the first key that differs is greater (or less).
     *
     * @param  Builder<Scene>  $query
     * @param  array<string, int>  $keys
     */
    private static function whereBeyond(Builder $query, array $keys, string $operator): void
    {
        $equal = [];

        foreach ($keys as $column => $value) {
            $query->orWhere(function (Builder $branch) use ($equal, $column, $operator, $value) {
                foreach ($equal as $equalColumn => $equalValue) {
                    $branch->where($equalColumn, $equalValue);
                }

                $branch->where($column, $operator, $value);
            });

            $equal[$column] = $value;
        }
    }
}
