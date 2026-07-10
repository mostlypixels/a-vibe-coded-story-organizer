<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Reorders a model within its ordered set of siblings by swapping its `position`
 * with the adjacent sibling's. This is the one place the "move up / move down"
 * logic lives — it was previously copied verbatim across the Act, Chapter, and
 * Scene controllers, differing only in the column that scopes the sibling set.
 *
 * A using model declares that scope column via {@see siblingScopeColumn()} (e.g.
 * `project_id` for acts, `act_id` for chapters, `chapter_id` for scenes); the swap
 * is otherwise identical. The two-row update runs in a transaction so the two
 * positions can never be left half-swapped.
 *
 * @property int $position
 */
trait HasSiblingPosition
{
    /**
     * The foreign-key column that scopes a model to its ordered sibling set. Acts
     * are ordered within a project, chapters within an act, scenes within a chapter.
     */
    abstract protected function siblingScopeColumn(): string;

    /**
     * Move this model one step earlier in its sibling set (towards position 1).
     */
    public function moveUp(): void
    {
        $this->swapWithAdjacentSibling('<', 'desc');
    }

    /**
     * Move this model one step later in its sibling set.
     */
    public function moveDown(): void
    {
        $this->swapWithAdjacentSibling('>', 'asc');
    }

    /**
     * Swap positions with the nearest sibling on one side. `$operator` selects the
     * side (`<` = the one just before, `>` = the one just after) and `$direction`
     * orders the candidates so `first()` returns the *adjacent* one. A no-op when
     * this model is already at that end of the set.
     */
    protected function swapWithAdjacentSibling(string $operator, string $direction): void
    {
        $scopeColumn = $this->siblingScopeColumn();

        $sibling = static::query()
            ->where($scopeColumn, $this->{$scopeColumn})
            ->where('position', $operator, $this->position)
            ->orderBy('position', $direction)
            ->first();

        if ($sibling === null) {
            return;
        }

        DB::transaction(function () use ($sibling) {
            [$this->position, $sibling->position] = [$sibling->position, $this->position];
            $this->save();
            $sibling->save();
        });
    }
}
