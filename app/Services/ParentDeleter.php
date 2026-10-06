<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a parent from the "move or delete" dialog: a book with its acts, an act
 * with its chapters, or a chapter with its scenes. The writer can move the
 * children to another parent first.
 *
 * Two pitfalls make the move worth one shared place:
 *
 *  - **`position` is not reassigned on a plain parent change.** Act/Chapter/Scene
 *    only auto-assign `position` in their `creating()` hook, so a moved child
 *    keeps whatever number it had — colliding with the destination's existing
 *    children. Each child is therefore appended after the destination's current
 *    maximum, in ascending original order, so relative order survives and no two
 *    siblings share a position.
 *  - **The foreign key is not mass-assignable.** `act_id`/`chapter_id` are absent
 *    from `$fillable` on purpose, so `update(['act_id' => …])` is *silently*
 *    dropped — a no-op that looks like a successful move. Reparenting has to go
 *    through the relationship's `associate()`.
 */
class ParentDeleter
{
    /**
     * Delete `$parent`. When `$destination` is set, its children move there first.
     * The move and the delete commit together.
     *
     * @param  string  $childrenRelation  The HasMany relation on both parents, e.g. `chapters`.
     * @param  string  $parentRelation  The inverse BelongsTo on the child, e.g. `act`.
     */
    public function delete(Model $parent, ?Model $destination, string $childrenRelation, string $parentRelation): void
    {
        DB::transaction(function () use ($parent, $destination, $childrenRelation, $parentRelation) {
            if ($destination !== null) {
                $this->moveChildren($parent, $destination, $childrenRelation, $parentRelation);
            }

            // The model cascade deletes any children that did not move.
            $parent->delete();
        });
    }

    private function moveChildren(Model $from, Model $to, string $childrenRelation, string $parentRelation): void
    {
        // Each save raises the destination maximum, so the next child lands after it.
        $from->{$childrenRelation}()
            ->orderBy('position')
            ->get()
            ->each(function (Model $child) use ($to, $parentRelation) {
                $child->moveToEndOf($to, $parentRelation);
                $child->save();
            });
    }
}
