<?php

namespace App\Services;

use App\Enums\RevisionOrigin;
use App\Events\SceneContentsChanged;
use App\Exceptions\RevisionConflictException;
use App\Models\Scene;
use App\Models\User;
use App\Support\AutosavableFields;
use App\Support\AutosaveResult;
use App\Support\FieldHash;
use App\Support\WordCounter;
use Illuminate\Database\Eloquent\Model;

/**
 * Saves one autosaved field and records its automatic revision.
 *
 * The server is the sole hash authority: the result hash is always computed
 * from the value actually persisted (post-mutator — e.g. a rich field's
 * `SanitizesRichHtml` set-mutator), never an echo of what the client sent.
 * Concretely, this is what stops a rich-HTML field's *second* autosave from
 * 409ing forever — if the client instead hashed what it sent, that hash would
 * never match the sanitized, already-differently-shaped stored value.
 */
class FieldAutosaver
{
    public function __construct(
        private readonly RevisionRecorder $recorder,
        private readonly SceneReferenceMatcher $matcher,
    ) {}

    /**
     * @param  bool  $runMatcher  True only for a coarse trigger (blur, Ctrl-S, submit), never a bare debounce tick.
     *
     * @throws RevisionConflictException When the stored value no longer matches `$baseHash`.
     */
    public function save(Model $model, string $field, ?string $value, string $baseHash, User $user, bool $runMatcher = false): AutosaveResult
    {
        $currentValue = (string) ($model->getAttribute($field) ?? '');

        if ($baseHash !== FieldHash::of($currentValue)) {
            throw RevisionConflictException::valueChangedElsewhere($field);
        }

        // The save below overwrites both of these, and the baseline seeded further
        // down needs them as they are now: it stands for the value the writer
        // started from, and for the moment that value started to hold.
        $heldSince = $model->updated_at;

        $model->{$field} = $value ?? '';
        $model->save(); // mutators run here, e.g. SanitizesRichHtml for rich fields.

        // Read back from memory, not with a fresh() round-trip. SanitizesRichHtml is
        // an `Attribute::make(set:)` mutator, so it ran at *assignment* above and the
        // in-memory attribute already holds exactly what was written — re-SELECTing
        // the row would return the same string at the cost of reading every column,
        // Scene.contents included. It is also the safer of the two: fresh() would
        // hand back a concurrent writer's value, and this endpoint must hash what
        // *it* stored — the server is the sole hash authority.
        $storedValue = (string) ($model->getAttribute($field) ?? '');

        // Autosave only ever records origin: automatic, and skips the write
        // entirely when the persisted value didn't change — so typing something and
        // undoing it leaves no trace. The full-form Save button's permanent, labeled
        // manual checkpoint is recorded separately, server-side, by the entity
        // controllers' update() via RevisionRecorder::recordManualChanges().
        // The baseline is seeded from the values captured above, and only inside
        // this branch: a save that changed nothing must leave the field with no
        // revisions at all, baseline included.
        $recorded = null;

        if ($storedValue !== $currentValue) {
            $this->recorder->ensureBaseline($model, $field, $currentValue, $heldSince);

            $recorded = $this->recorder->record($model, $field, $storedValue, $user, RevisionOrigin::Automatic);
        }

        $isSceneContents = $model instanceof Scene && $field === 'contents';

        // SceneContentsChanged is a published seam for other features.
        // Nothing listens to it today.
        if ($runMatcher && $isSceneContents) {
            $this->matcher->syncScene($model);

            SceneContentsChanged::dispatch($model);
        }

        return new AutosaveResult(
            value: $storedValue,
            wordCount: $this->wordCount($model, $field, $storedValue, $isSceneContents),
            // record() already returned the row it wrote or coalesced into, so the
            // lookup is only needed for the no-op branch, where the client still
            // wants to know which revision its text currently corresponds to.
            revisionId: ($recorded ?? $this->recorder->lastRevisionFor($model, $field))?->id,
        );
    }

    /**
     * The authoritative word count, read after save — never computed from
     * what the client sent, the same rule the hash follows.
     * Scene.contents is the one field with a stored column: Scene's
     * `saving` hook has already recounted it as part of this save, so
     * reading $model->word_count reuses that number instead of recounting it a
     * second time. Every other field (including Scene.description/notes, which
     * share the model but not that column) has nothing stored to read, so it is
     * counted here, on the value that was actually persisted.
     */
    private function wordCount(Model $model, string $field, string $storedValue, bool $isSceneContents): int
    {
        if ($isSceneContents) {
            return $model->word_count;
        }

        return WordCounter::count($storedValue, AutosavableFields::kindOf(AutosavableFields::slugFor($model::class), $field));
    }
}
