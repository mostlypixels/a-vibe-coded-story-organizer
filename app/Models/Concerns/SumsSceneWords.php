<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Reads the `withSum('scenes as word_count', 'word_count')` total as a number.
 *
 * SQL `SUM` has no rows to add for an act or a chapter that holds no scene, so
 * it answers NULL and the page shows a blank where it must show "0 words". Each
 * index looped over its rows to correct that, which is a step a new index can
 * forget. The reader now cannot see the NULL.
 *
 * > [!NOTE]
 * > `word_count` is not a column on these models. It exists only on a query
 * > that adds the aggregate. Without one, this answers 0.
 */
trait SumsSceneWords
{
    protected function wordCount(): Attribute
    {
        return Attribute::get(fn (mixed $value): int => (int) $value);
    }
}
