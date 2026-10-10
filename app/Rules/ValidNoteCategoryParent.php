<?php

namespace App\Rules;

use App\Models\NoteCategory;
use App\Models\Project;
use App\Support\NoteCategoryTree;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The parent of a note category: in the same project, not the category itself,
 * not one of its descendants, and low enough that the whole subtree fits in
 * {@see NoteCategory::MAX_DEPTH} levels.
 *
 * `$category` is null on create. A null value (top level) never reaches this rule.
 */
final class ValidNoteCategoryParent implements ValidationRule
{
    public function __construct(
        private Project $project,
        private ?NoteCategory $category = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tree = NoteCategoryTree::for($this->project);

        // A foreign or missing id fails like any unknown id.
        if (! is_numeric($value) || ! $tree->has((int) $value)) {
            $fail('validation.exists')->translate();

            return;
        }

        $parentId = (int) $value;
        $subtreeHeight = 1;

        if ($this->category !== null) {
            $id = $this->category->getKey();

            if ($parentId === $id || in_array($parentId, $tree->descendantIds($id), true)) {
                $fail('A category cannot be moved under itself or one of its sub-categories.')->translate();

                return;
            }

            $subtreeHeight = $tree->heightOf($id);
        }

        if ($tree->depthOf($parentId) + $subtreeHeight > NoteCategory::MAX_DEPTH) {
            $fail('Categories can nest :max levels deep.')->translate(['max' => NoteCategory::MAX_DEPTH]);
        }
    }
}
