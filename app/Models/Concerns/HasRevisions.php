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
 * walks to the owning Project" rule). Each using model implements it by walking its
 * own relation path to `Project` — see data-model.md's resolution table:
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
}
