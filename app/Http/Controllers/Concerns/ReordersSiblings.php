<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Concerns\HasSiblingPosition;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * The controller half of a "move up / move down" action.
 *
 * {@see HasSiblingPosition} owns the swap itself; this owns the two things every
 * one of the six move actions (Act, Chapter and Scene × up, down) did around it:
 * authorize against the owning Project, then move. That pairing was repeated
 * verbatim, and forgetting the authorize half is exactly the kind of hole
 * CLAUDE.md's "every action authorizes" rule exists to prevent — so it is now
 * impossible to call the move without it.
 *
 * The **response is deliberately not absorbed**: Act and Chapter always redirect
 * back, while Scene's pair also answers JSON for the Story overview's AJAX
 * reorder. Folding that in would mean either giving Act and Chapter a JSON
 * branch nothing requests, or a second method — both worse than the one line
 * each caller writes itself.
 */
trait ReordersSiblings
{
    /**
     * Authorize the reorder against the owning Project, then move `$model` one
     * step through its sibling set.
     *
     * @param  Model  $model  A model using {@see HasSiblingPosition}.
     * @param  bool  $up  Towards position 1, rather than away from it.
     */
    protected function reorderSibling(Model $model, Project $project, bool $up): void
    {
        $this->authorize('update', $project);

        $up ? $model->moveUp() : $model->moveDown();
    }
}
