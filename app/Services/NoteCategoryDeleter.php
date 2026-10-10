<?php

namespace App\Services;

use App\Models\NoteCategory;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a note category. Its notes and sub-categories move up one level, to
 * the parent or, for a top-level category, to the root. Nothing is lost.
 *
 * The moves use the base query builder on purpose. A category move is not an
 * edit of the note, so `updated_at` must not change and reorder the index.
 */
class NoteCategoryDeleter
{
    public function delete(NoteCategory $category): void
    {
        DB::transaction(function () use ($category) {
            $parentId = $category->parent_id;

            $category->notes()->toBase()->update(['note_category_id' => $parentId]);
            $category->children()->toBase()->update(['parent_id' => $parentId]);

            $category->delete();
        });
    }
}
