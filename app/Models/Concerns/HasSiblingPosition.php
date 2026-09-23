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
 * is otherwise identical. The renumber and the swap run in one transaction so
 * the positions can never be left half-written.
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
        $this->swapWithAdjacentSibling(-1);
    }

    /**
     * Move this model one step later in its sibling set.
     */
    public function moveDown(): void
    {
        $this->swapWithAdjacentSibling(1);
    }

    /**
     * Shift every sibling positioned after this model down by one, opening a gap
     * for a new row right after it. Returns the freed position (`$this->position
     * + 1`) for the caller to insert at. Runs inside the caller's transaction —
     * unlike {@see swapWithAdjacentSibling()}, which owns its own.
     */
    public function makeRoomAfter(): int
    {
        $scopeColumn = $this->siblingScopeColumn();

        static::query()
            ->where($scopeColumn, $this->{$scopeColumn})
            ->where('position', '>', $this->position)
            ->increment('position');

        return $this->position + 1;
    }

    /**
     * Swap positions with the adjacent sibling in display order (position, then
     * id). `$step` is -1 for the one before and +1 for the one after. A no-op
     * when this model is already at that end of the set.
     */
    protected function swapWithAdjacentSibling(int $step): void
    {
        $scopeColumn = $this->siblingScopeColumn();

        DB::transaction(function () use ($scopeColumn, $step) {
            $siblings = static::query()
                ->where($scopeColumn, $this->{$scopeColumn})
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->values();

            // `position` has no unique index, so ties and gaps can occur. A tie
            // hides the neighbour from a compare on position. Thus, first set
            // the set to 1..n in display order (#173).
            foreach ($siblings as $index => $sibling) {
                $sibling->position = $index + 1;
                $sibling->save();
            }

            $index = $siblings->search(fn (self $sibling) => $sibling->is($this));
            $neighbour = $siblings->get($index + $step);

            if ($neighbour !== null) {
                [$neighbour->position, $siblings[$index]->position] = [$siblings[$index]->position, $neighbour->position];
                $neighbour->save();
                $siblings[$index]->save();
            }

            // The caller reads this instance, for example in the JSON reply.
            $this->position = $siblings[$index]->position;
            $this->syncOriginalAttribute('position');
        });
    }
}
