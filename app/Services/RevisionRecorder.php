<?php

namespace App\Services;

use App\Enums\RevisionOrigin;
use App\Models\Revision;
use App\Models\User;
use App\Support\AutosavableFields;
use App\Support\RevisionSummary;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one place the application writes to the `revisions` table.
 *
 * Called by App\Http\Controllers\FieldAutosaveController and by the baseline
 * backfill migration. Both must use this one code path. Never
 * copy this logic into a migration — the live path and the backfill would then
 * drift.
 *
 * Deliberately does *not* decide whether to write at all. The byte-identical
 * no-op check ("typing something and undoing it leaves no trace") is the
 * caller's job: it compares the incoming value against the entity's current
 * column value before it calls record(). This class only knows how to coalesce
 * and how to seed a baseline.
 *
 * It is also the only place `save_id` — the *save point* grouping key the
 * history/compare/revert screens address — is minted. That requires the
 * recorder to be **one instance per request**: it is registered as
 * `$this->app->scoped()` in App\Providers\AppServiceProvider.
 */
class RevisionRecorder
{
    /**
     * The save-point id minted for each entity touched during this request,
     * keyed by `"<class>:<key>"`. Deliberately per (request, entity) rather
     * than per request: App\Services\Import\ProjectGraphImporter writes
     * revisions for hundreds of entities in a single request, and grouping
     * them into one save point would make "Undo this save" offer to revert an
     * entire imported project.
     *
     * Instance state, not static and not session-backed — it must reset
     * between requests, which is exactly the `scoped()` binding's lifetime.
     *
     * @var array<string, string>
     */
    private array $saveIds = [];

    public function __construct(private readonly RevisionSummarizer $summarizer) {}

    /**
     * The save point every row written for `$entity` during this request
     * belongs to, minted on first use.
     */
    public function currentSaveId(Model $entity): string
    {
        return $this->saveIds[$this->saveKey($entity)] ??= (string) Str::ulid();
    }

    /**
     * Deliberately open a *new* save point for `$entity` inside a request that
     * has already written to it — used by the whole-save revert, whose result
     * must be a save point of its own rather than joining the rows it was
     * triggered from.
     */
    public function startNewSave(Model $entity): void
    {
        unset($this->saveIds[$this->saveKey($entity)]);
    }

    /**
     * Record a new value for one revisionable field, coalescing with an
     * already-open automatic revision when one exists.
     *
     * `origin: automatic` revisions coalesce: if an automatic revision for
     * this exact (entity, field) was created within the field's configured
     * window (App\Support\AutosavableFields::windowSeconds()), that row's
     * `value`/`size_bytes` are overwritten in place rather than inserting a
     * new row. Every other origin (manual, revert, import) always inserts a
     * fresh row — this is what makes a form-submit Save a permanent,
     * individually visible entry even seconds after an autosave.
     *
     * A coalescing update keeps the row's original `save_id`, exactly as it
     * already keeps its original `created_at`: it is the same continuing
     * editing burst, and rewriting either would make the row claim to be
     * something it is not. Accepted consequence (see documentation/
     * architecture.md): if a save touches three fields and one of them
     * coalesces into a still-open row from an earlier burst, that field lands
     * in the *earlier* save point, so the group the writer thinks of as "the
     * save I just made" lists two fields, not three.
     */
    public function record(
        Model $entity,
        string $field,
        string $value,
        User $user,
        RevisionOrigin $origin,
        ?string $label = null,
    ): Revision {
        $this->ensureBaseline($entity, $field);

        $open = $origin === RevisionOrigin::Automatic
            ? $this->openAutomaticRevision($entity, $field)
            : null;

        if ($open !== null) {
            // The row's value is being replaced, so its stored summary is now
            // describing a diff that no longer exists. Recomputed against the
            // row *before* this one — a coalescing row is never its own
            // predecessor, however many times the burst overwrites it.
            $summary = $this->summarize($entity, $field, $value, $this->predecessorValue($entity, $field, $open));

            $open->update([
                'value' => $value,
                'size_bytes' => strlen($value),
                'summary_html' => $summary->summaryHtml,
                'change_count' => $summary->changeCount,
            ]);

            return $open;
        }

        // Resolved after ensureBaseline() above, so the first real edit to a
        // field is summarised against the baseline it just seeded rather than
        // against nothing.
        $summary = $this->summarize($entity, $field, $value, $this->predecessorValue($entity, $field));

        return $entity->revisions()->create([
            'field' => $field,
            'save_id' => $this->currentSaveId($entity),
            'value' => $value,
            'size_bytes' => strlen($value),
            'summary_html' => $summary->summaryHtml,
            'change_count' => $summary->changeCount,
            'project_id' => $entity->revisionProject()->id,
            'user_id' => $user->id,
            'label' => $label,
            'origin' => $origin,
            'created_at' => now(),
        ]);
    }

    /**
     * The auto-generated label every full-form Save button's manual checkpoint
     * gets, e.g. "Saved 24 July 10:43" — the same `d F H:i` format
     * RevisionController's revert action already uses for its own
     * auto-generated "Reverted to :date" label, kept as one shared format
     * rather than each caller picking its own.
     */
    public static function manualSaveLabel(): string
    {
        return __('Saved :date', ['date' => now()->format('d F H:i')]);
    }

    /**
     * Record a manual checkpoint for every field in `$before` whose value
     * changed compared to `$entity`'s current (already-saved) value.
     *
     * `$before` is App\Support\AutosavableFields::snapshotFieldsBeforeUpdate()'s
     * output, taken by the caller *before* it applied the form's data to
     * `$entity` — this method has no other way to know the pre-edit value once
     * that update has already overwritten it. A full-form Save button commonly
     * covers several autosaved fields in one submit (e.g. Project's
     * description/rights/dedication/acknowledgements/preface/postface); only
     * the fields a writer actually touched get a new row, so clicking Save
     * after editing just one of them doesn't also spam empty-diff manual rows
     * for the rest.
     *
     * `$label` defaults to {@see self::manualSaveLabel()} — the only label
     * every caller ever passes, so it lives here rather than being repeated at
     * each call site. (It cannot be a parameter default: PHP default values
     * must be constant expressions, and the label embeds `now()`.)
     */
    public function recordManualChanges(Model $entity, array $before, User $user, ?string $label = null): void
    {
        $label ??= self::manualSaveLabel();

        foreach ($before as $field => $previousValue) {
            $currentValue = (string) ($entity->getAttribute($field) ?? '');

            if ($currentValue === $previousValue) {
                continue;
            }

            $this->record($entity, $field, $currentValue, $user, RevisionOrigin::Manual, $label);
        }
    }

    /**
     * Seed a `baseline` revision holding the entity's *current* (pre-edit)
     * value for this field, but only if no revision at all exists yet for
     * this (entity, field) pair — a no-op on every later call.
     *
     * `created_at` is stamped `$entity->updated_at`, not `now()`: that value
     * provably held from that timestamp onward, whereas stamping `now()`
     * would misrepresent the entire pre-baseline era for compare-by-date.
     * `user_id` is the project owner, not any particular editor, since no one
     * "wrote" the baseline.
     *
     * Skipped entirely when the field's current value is null/empty — an
     * empty field has nothing worth preserving.
     *
     * The baseline gets a `save_id` all of its own rather than joining the
     * save that triggered the seeding: it is the pre-edit state, not part of
     * that save, and showing it inside that save point would attribute an
     * untouched value to the writer who happened to edit next.
     */
    public function ensureBaseline(Model $entity, string $field): void
    {
        if ($entity->revisions()->where('field', $field)->exists()) {
            return;
        }

        $current = $entity->getAttribute($field);

        if ($current === null || $current === '') {
            return;
        }

        $entity->revisions()->create([
            'field' => $field,
            'save_id' => (string) Str::ulid(),
            'value' => $current,
            'size_bytes' => strlen($current),
            // A baseline has nothing before it, so there is no change to
            // summarise. The history list renders these as "Initial value"
            // rather than as an empty diff.
            'summary_html' => null,
            'change_count' => 0,
            'project_id' => $entity->revisionProject()->id,
            'user_id' => $entity->revisionProject()->user_id,
            'label' => null,
            'origin' => RevisionOrigin::Baseline,
            'created_at' => $entity->updated_at,
        ]);
    }

    /**
     * The most recently recorded revision for this (entity, field) pair, if
     * any — used by callers to decide whether a byte-identical write can be
     * skipped, and to report the revision id a save just touched.
     */
    public function lastRevisionFor(Model $entity, string $field): ?Revision
    {
        return $entity->revisions()->where('field', $field)->latest('created_at')->first();
    }

    /**
     * The `value` of {@see self::lastRevisionFor()}, or null if this
     * (entity, field) pair has no revision yet.
     */
    public function lastValueFor(Model $entity, string $field): ?string
    {
        return $this->lastRevisionFor($entity, $field)?->value;
    }

    /**
     * Summarise a value against what it replaced, for the row's stored
     * `summary_html` / `change_count`.
     *
     * > [!IMPORTANT]
     * > Wrapped, and deliberately so: a revision without a summary is a
     * > cosmetic problem, a lost save is not. Whatever the diff layer trips
     * > over — a malformed stored value, an unsupported construct — the
     * > writer's text still reaches the database, and the row simply has
     * > nothing to say about itself on the history list.
     */
    private function summarize(Model $entity, string $field, string $value, ?string $previousValue): RevisionSummary
    {
        try {
            $kind = AutosavableFields::kindOf(AutosavableFields::slugFor($entity::class), $field);

            return $this->summarizer->summarize($kind, $previousValue, $value);
        } catch (Throwable $exception) {
            Log::warning('Could not summarize a revision; storing it without a summary.', [
                'entity' => $entity::class,
                'entity_id' => $entity->getKey(),
                'field' => $field,
                'exception' => $exception,
            ]);

            return new RevisionSummary(null, 0);
        }
    }

    /**
     * The value this (entity, field) held before the row being written — the
     * newest revision strictly older than it.
     *
     * `$before` is passed only on a coalescing update, where the row being
     * rewritten already exists and must be stepped over. On an insert the row
     * does not exist yet, so the newest revision *is* the predecessor.
     *
     * Reads one column of one row: the write path already touches this
     * neighbourhood, and it buys a history list that never diffs anything.
     */
    private function predecessorValue(Model $entity, string $field, ?Revision $before = null): ?string
    {
        $revisions = $entity->revisions()->where('field', $field);

        if ($before !== null) {
            // Ordering is by (created_at, id), so "older than" has to be too —
            // a burst can write several rows inside the same second.
            $revisions->where(fn (Builder $query) => $query
                ->where('created_at', '<', $before->created_at)
                ->orWhere(fn (Builder $tie) => $tie
                    ->where('created_at', $before->created_at)
                    ->where('id', '<', $before->id)));
        }

        return $revisions->latest('created_at')->latest('id')->value('value');
    }

    /**
     * The {@see self::$saveIds} memo key for one entity.
     */
    private function saveKey(Model $entity): string
    {
        return $entity::class.':'.$entity->getKey();
    }

    /**
     * The still-open automatic revision for this (entity, field) pair, if
     * its coalescing window (App\Support\AutosavableFields::windowSeconds())
     * hasn't closed yet.
     */
    private function openAutomaticRevision(Model $entity, string $field): ?Revision
    {
        $slug = AutosavableFields::slugFor($entity::class);
        $window = AutosavableFields::windowSeconds($slug, $field);

        return $entity->revisions()
            ->where('field', $field)
            ->where('origin', RevisionOrigin::Automatic)
            ->where('created_at', '>=', now()->subSeconds($window))
            ->latest('created_at')
            ->first();
    }
}
