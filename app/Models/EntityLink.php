<?php

namespace App\Models;

use App\Enums\LinkTargetType;
use App\Services\EntityLinkIndex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * One `entity:` link in saved text: a derived index that {@see EntityLinkIndex}
 * writes. Never edit a row by hand.
 *
 * `source_type` is a morph class. `target_type` is a {@see LinkTargetType} value. Neither
 * has a foreign key, so a deleted source or target must delete its rows.
 */
class EntityLink extends Model
{
    protected $table = 'entity_links';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * Deletes the rows where an entity that the query selects is the source or the target.
     *
     * A database cascade fires no model events. Thus a parent's `deleting`
     * hook calls this for its children before the cascade removes them.
     */
    public static function deleteFor(Builder|Relation $entities): void
    {
        $query = $entities instanceof Relation ? $entities->getQuery() : $entities;
        $model = $query->getModel();
        $ids = (clone $query)->select($model->getQualifiedKeyName());
        $targetType = LinkTargetType::tryFromModel($model);

        static::query()
            ->where(fn (Builder $q) => $q->where('source_type', $model->getMorphClass())->whereIn('source_id', $ids))
            ->when($targetType, fn (Builder $q) => $q->orWhere(
                fn (Builder $q) => $q->where('target_type', $targetType?->value)->whereIn('target_id', clone $ids),
            ))
            ->delete();
    }
}
