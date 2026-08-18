<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Moves a parent's ordered children onto a new parent, for the "move or delete"
 * dialog's reassignment branch (an act's chapters → another act, a chapter's
 * scenes → another chapter).
 *
 * Both destroy actions ran the same algorithm and carried the same explanation
 * of the same two pitfalls. Those pitfalls are the reason this is worth sharing:
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
 *
 * The caller owns the transaction. Reassignment and deletion must commit together.
 */
trait ReparentsChildren
{
    /**
     * Append every one of `$from`'s children to the end of `$to`'s ordered set.
     *
     * @param  string  $childrenRelation  The HasMany relation on both parents, e.g. `chapters`.
     * @param  string  $parentRelation  The inverse BelongsTo on the child, e.g. `act`.
     */
    protected function reparentChildren(Model $from, Model $to, string $childrenRelation, string $parentRelation): void
    {
        $nextPosition = $to->{$childrenRelation}()->max('position') + 1;

        $from->{$childrenRelation}()
            ->orderBy('position')
            ->get()
            ->each(function (Model $child) use ($to, $parentRelation, &$nextPosition) {
                $child->position = $nextPosition++;
                $child->{$parentRelation}()->associate($to);
                $child->save();
            });
    }
}
