<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Application-wide revision retention policy — a singleton.
 *
 * Exactly one row ever exists: the whole application shares one retention
 * window for `automatic`, unlabeled revisions (Revision::prunable() reads
 * this row's retention_days instead of a raw config value, so the window is
 * admin-configurable — task 13 adds the confirm-gated settings form). Like
 * {@see ImportSetting} this model is global (no owning Project or User), so
 * it is deliberately outside ProjectPolicy's authorization walk — any
 * authenticated user may edit it, behind the `access-admin` gate. See
 * documentation/architecture.md.
 *
 * Always read the singleton through {@see self::current()}; never `new` a
 * second row.
 */
class RevisionSetting extends Model
{
    protected $fillable = [
        'retention_days',
    ];

    /**
     * Return the singleton row, lazily creating it from config defaults on
     * first read (a fresh install has no row yet, but Revision::prunable()
     * must still have a working retention window).
     *
     * Deliberately NOT memoised: the value can change within a single
     * request lifecycle (e.g. a settings update immediately followed by a
     * prune-count query in the same test), and the single-row query is
     * trivial, matching ImportSetting::current()'s own reasoning.
     */
    public static function current(): self
    {
        return static::firstOr(fn () => static::create([
            'retention_days' => config('revisions.retention_days'),
        ]));
    }
}
