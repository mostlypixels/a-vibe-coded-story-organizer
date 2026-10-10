<?php

namespace App\Support;

use App\Models\NoteCategory;
use App\Models\Project;
use App\Rules\ValidNoteCategoryParent;
use Illuminate\Support\Collection;

/**
 * A project's note categories as a tree. One query; PHP builds the shape.
 *
 * The sidebar and the indented selects read {@see flat()}. The parent rule reads
 * the depth, height and descendant helpers, so it needs no query per level.
 */
final class NoteCategoryTree
{
    /** @var Collection<int, NoteCategory> */
    private Collection $byId;

    /** @var Collection<int|string, Collection<int, NoteCategory>> Children by parent id; roots under "". */
    private Collection $byParent;

    /** @param  Collection<int, NoteCategory>  $categories */
    private function __construct(Collection $categories)
    {
        $sorted = $categories->sortBy(fn (NoteCategory $category) => mb_strtolower($category->name), SORT_NATURAL)->values();

        $this->byId = $sorted->keyBy('id');
        $this->byParent = $sorted->groupBy(fn (NoteCategory $category) => $category->parent_id ?? '');
    }

    public static function for(Project $project): self
    {
        return new self($project->noteCategories()->get());
    }

    /**
     * Depth first, siblings by name. `depth` is 1 for a root category.
     *
     * @return list<array{category: NoteCategory, depth: int}>
     */
    public function flat(): array
    {
        return $this->walk('', 1);
    }

    /** Levels from the root down to the category: a root category is 1. */
    public function depthOf(int $id): int
    {
        $depth = 1;
        $parentId = $this->byId->get($id)?->parent_id;

        // The step cap keeps bad data from looping.
        while ($parentId !== null && $depth <= NoteCategory::MAX_DEPTH) {
            $depth++;
            $parentId = $this->byId->get($parentId)?->parent_id;
        }

        return $depth;
    }

    /** Levels in the category's own subtree: a leaf is 1. */
    public function heightOf(int $id, int $guard = 0): int
    {
        if ($guard > NoteCategory::MAX_DEPTH) {
            return 1;
        }

        $tallest = 0;

        foreach ($this->byParent->get($id, collect()) as $child) {
            $tallest = max($tallest, $this->heightOf($child->id, $guard + 1));
        }

        return $tallest + 1;
    }

    /** @return list<int> */
    public function descendantIds(int $id): array
    {
        $ids = [];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($this->byParent->get($current, collect()) as $child) {
                if (! in_array($child->id, $ids, true)) {
                    $ids[] = $child->id;
                    $queue[] = $child->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Category ids that cannot be the parent of `$categoryId`, or of a new category when null.
     * The dialog disables them. {@see ValidNoteCategoryParent} still checks on the server.
     *
     * @return list<int>
     */
    public function blockedParentIds(?int $categoryId = null): array
    {
        $blocked = $categoryId === null ? [] : [$categoryId, ...$this->descendantIds($categoryId)];
        $height = $categoryId === null ? 1 : $this->heightOf($categoryId);

        foreach ($this->byId->keys() as $id) {
            if ($this->depthOf($id) + $height > NoteCategory::MAX_DEPTH) {
                $blocked[] = $id;
            }
        }

        return array_values(array_unique($blocked));
    }

    public function has(int $id): bool
    {
        return $this->byId->has($id);
    }

    /** "Planning › Research" for a category id, or null for none. */
    public function pathOf(?int $id): ?string
    {
        $names = [];

        while ($id !== null && $this->byId->has($id) && count($names) <= NoteCategory::MAX_DEPTH) {
            array_unshift($names, $this->byId->get($id)->name);
            $id = $this->byId->get($id)->parent_id;
        }

        return $names === [] ? null : implode(' › ', $names);
    }

    /** @return list<array{category: NoteCategory, depth: int}> */
    private function walk(int|string $parentId, int $depth): array
    {
        if ($depth > NoteCategory::MAX_DEPTH) {
            return [];
        }

        $rows = [];

        foreach ($this->byParent->get($parentId, collect()) as $category) {
            $rows[] = ['category' => $category, 'depth' => $depth];
            array_push($rows, ...$this->walk($category->id, $depth + 1));
        }

        return $rows;
    }
}
