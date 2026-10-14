<?php

namespace App\Models\Concerns;

use App\Services\EntityLinkIndex;
use App\Support\EntityLinks;

/**
 * Keeps the `entity_links` rows of a model equal to its saved text. Applied to every
 * model with a link field (see {@see EntityLinkIndex::fieldsOf()}), and to every other
 * link target, so that a deleted target loses its rows.
 *
 * A hook, not a controller call: the rows come from the row's own fields. Every save
 * path (form, autosave, revert, duplicate) calls `save()`, so each one syncs.
 *
 * > [!WARNING]
 * > A database cascade skips these hooks. Book, Act and Chapter call
 * > `EntityLink::deleteFor()` for their cascaded children themselves.
 */
trait HasEntityLinks
{
    public static function bootHasEntityLinks(): void
    {
        static::saved(function (self $model): void {
            $fields = EntityLinkIndex::fieldsOf(static::class);

            if ($fields === [] || ! self::linksMayHaveChanged($model, $fields)) {
                return;
            }

            app(EntityLinkIndex::class)->sync($model);
        });

        // `deleted`, not `deleting`: a failed delete keeps its rows.
        static::deleted(function (self $model): void {
            app(EntityLinkIndex::class)->forget($model);
        });
    }

    /**
     * Eloquent fills `changes` on an update only. A new row has no index rows yet, so
     * it syncs only when its text holds a link.
     *
     * @param  list<string>  $fields
     */
    private static function linksMayHaveChanged(self $model, array $fields): bool
    {
        // `wasRecentlyCreated` stays true for later saves of the same instance.
        if ($model->wasChanged($fields)) {
            return true;
        }

        if (! $model->wasRecentlyCreated) {
            return false;
        }

        foreach ($fields as $field) {
            if (EntityLinks::find((string) $model->getAttribute($field)) !== []) {
                return true;
            }
        }

        return false;
    }
}
