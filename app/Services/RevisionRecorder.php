<?php

namespace App\Services;

use App\Enums\RevisionOrigin;
use App\Models\Revision;
use App\Models\User;
use App\Support\AutosavableFields;
use App\Support\RevisionSummary;
use DateTimeInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes revisions, coalesces autosaves, seeds baselines, and assigns save IDs.
 *
 * Callers skip unchanged values. The scoped service instance groups all fields
 * that one request saves on the same entity.
 */
class RevisionRecorder
{
    /** @var array<string, string> One save ID per entity in this request. */
    private array $saveIds = [];

    public function __construct(private readonly RevisionSummarizer $summarizer) {}

    /** Returns or creates this request's save ID for an entity. */
    public function currentSaveId(Model $entity): string
    {
        return $this->saveIds[$this->saveKey($entity)] ??= (string) Str::ulid();
    }

    /** Starts a separate save point for an undo. */
    public function startNewSave(Model $entity): void
    {
        unset($this->saveIds[$this->saveKey($entity)]);
    }

    /**
     * Records one field value.
     *
     * Automatic revisions within the field's window update the open row. Other
     * origins always insert a row. Coalescing preserves the original time and save ID.
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
            // Recompute the summary against the row before the coalesced row.
            $summary = $this->summarize($entity, $field, $value, $this->predecessorValue($entity, $field, $open));

            $open->update([
                'value' => $value,
                'size_bytes' => strlen($value),
                'summary_html' => $summary->summaryHtml,
                'change_count' => $summary->changeCount,
            ]);

            return $open;
        }

        // The first edit compares against the baseline created above.
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

    /** Returns the standard label for a manual save. */
    public static function manualSaveLabel(): string
    {
        return __('Saved :date', ['date' => now()->format('d F H:i')]);
    }

    /**
     * Records changed fields from a full form as one manual checkpoint.
     *
     * The caller must capture `$before` and `$heldSince` before it updates the model.
     */
    public function recordManualChanges(
        Model $entity,
        array $before,
        User $user,
        ?string $label = null,
        ?DateTimeInterface $heldSince = null,
    ): void {
        $label ??= self::manualSaveLabel();

        foreach ($before as $field => $previousValue) {
            $currentValue = (string) ($entity->getAttribute($field) ?? '');

            if ($currentValue === $previousValue) {
                continue;
            }

            $this->ensureBaseline($entity, $field, $previousValue, $heldSince);

            $this->record($entity, $field, $currentValue, $user, RevisionOrigin::Manual, $label);
        }
    }

    /**
     * Seeds a separate baseline from the first non-empty pre-edit value.
     *
     * The baseline uses the time that value began to apply, not the current time.
     *
     * > [!WARNING]
     * > A caller that already saved must pass the pre-edit value and time.
     *
     * @param  string|null  $previousValue  The known pre-edit value.
     * @param  DateTimeInterface|null  $heldSince  When that value began to apply.
     */
    public function ensureBaseline(
        Model $entity,
        string $field,
        ?string $previousValue = null,
        ?DateTimeInterface $heldSince = null,
    ): void {
        if ($entity->revisions()->where('field', $field)->exists()) {
            return;
        }

        $current = $previousValue ?? $entity->getAttribute($field);
        $heldSince ??= $entity->updated_at;

        if ($current === null || $current === '') {
            return;
        }

        $entity->revisions()->create([
            'field' => $field,
            'save_id' => (string) Str::ulid(),
            'value' => $current,
            'size_bytes' => strlen($current),
            'summary_html' => null,
            'change_count' => 0,
            'project_id' => $entity->revisionProject()->id,
            'user_id' => $entity->revisionProject()->user_id,
            'label' => null,
            'origin' => RevisionOrigin::Baseline,
            'created_at' => $heldSince,
        ]);
    }

    /** Returns the latest revision for a field. */
    public function lastRevisionFor(Model $entity, string $field): ?Revision
    {
        return $entity->revisions()->where('field', $field)->latest('created_at')->first();
    }

    /** Returns the latest stored value for a field. */
    public function lastValueFor(Model $entity, string $field): ?string
    {
        return $this->lastRevisionFor($entity, $field)?->value;
    }

    /**
     * Builds the stored summary.
     *
     * > [!IMPORTANT]
     * > A summary failure must never prevent the revision write.
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

    /** Returns the value before a new or coalesced revision. */
    private function predecessorValue(Model $entity, string $field, ?Revision $before = null): ?string
    {
        $revisions = $entity->revisions()->where('field', $field);

        if ($before !== null) {
            // The ID breaks ties between revisions in the same second.
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

    /** Returns the open automatic revision within the field's window. */
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
