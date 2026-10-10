<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * One link between a note and an entity of the same project. The target is
 * polymorphic and has no foreign key, so deleting the target must delete this row.
 */
class Notable extends Model
{
    protected $table = 'notables';

    public $timestamps = false;

    /**
     * Deletes the links of each entity that the query selects.
     *
     * A database cascade fires no model events. Thus a parent's `deleting`
     * hook calls this for its children before the cascade removes them.
     */
    public static function deleteFor(Builder|Relation $entities): void
    {
        $query = $entities instanceof Relation ? $entities->getQuery() : $entities;
        $model = $query->getModel();

        static::query()
            ->where('notable_type', $model->getMorphClass())
            ->whereIn('notable_id', $query->select($model->getQualifiedKeyName()))
            ->delete();
    }
}
