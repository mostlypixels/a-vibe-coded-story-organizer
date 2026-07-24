<?php

namespace App\Models\Concerns;

use App\Models\Project;
use App\Models\Revision;
use App\Support\AutosavableFields;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Applied to every model registered in {@see AutosavableFields}: the
 * `revisions` relation itself, plus the contract every using model must satisfy to
 * make revision authorization possible.
 *
 * `revisionProject()` is the authorization boundary for the whole feature — the
 * autosave controller, the revision history/compare/revert actions, and the purge
 * preview all authorize via `ProjectPolicy` against whatever this method returns,
 * never against the revisionable model directly (CLAUDE.md's "authorization always
 * walks to the owning Project" rule). Reads (history, compare, the browser landing)
 * gate on `view`; writes (autosave, revert, purge) gate on `update` — set on purpose
 * so a future view-only collaborator could read history without being able to change
 * it, even though the two abilities resolve to the same owner today. Each using model
 * implements it by walking its own relation path to `Project` — see data-model.md's
 * resolution table:
 *
 *   - Act, Event, CodexEntry, Plotline: `return $this->project;`
 *   - Chapter: `return $this->act->project;`
 *   - Scene: `return $this->chapter->act->project;`
 *   - Project: `return $this;`
 */
trait HasRevisions
{
    /**
     * The Project that owns this revisionable entity — the authorization boundary
     * every revision-related action walks to before checking ProjectPolicy.
     */
    abstract public function revisionProject(): Project;

    /**
     * Every revision ever recorded for this entity, across all of its registered
     * fields, newest first.
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('created_at');
    }

    /**
     * The single column holding this model's human-readable title in the
     * revisions UI. `name` for every revisionable except Event, which overrides
     * this to `title` — the one place that exception lives now (previously it was
     * re-encoded in both RevisionController and ProjectRevisionsBrowser).
     *
     * Static so ProjectRevisionsBrowser can name the column at query-build time —
     * it selects `id` + this column instead of hydrating each entity's full row,
     * so it never loads a large rich/markdown field just to print a sidebar label.
     */
    public static function revisionDisplayColumn(): string
    {
        return 'name';
    }

    /**
     * This entity's own human-readable title for a revisions heading (e.g.
     * "Compare — Project 'Melusine' — Description"), read from
     * {@see self::revisionDisplayColumn()}. Falls back to `#<id>` so a heading
     * is never blank if that column is empty.
     */
    public function revisionDisplayName(): string
    {
        return (string) ($this->getAttribute(static::revisionDisplayColumn()) ?? '#'.$this->getKey());
    }
}
