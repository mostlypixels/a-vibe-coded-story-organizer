<?php

namespace App\Services;

use App\Enums\RevisionOrigin;
use App\Exceptions\RevisionConflictException;
use App\Models\Revision;
use App\Models\User;
use App\Support\AutosavableFields;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Restores old values for single-field reverts and whole-save undo.
 *
 * A revert adds a new revision. It never changes or removes existing history.
 */
class RevisionReverter
{
    public function __construct(private readonly RevisionRecorder $recorder) {}

    /**
     * Restores one field if its value still matches the page's base hash.
     *
     * @throws RevisionConflictException When the stored value changed.
     */
    public function revertField(Model $entity, Revision $revision, string $baseHash, User $user): string
    {
        $this->assertUnchanged($entity, $revision->field, $baseHash);

        return $this->restore(
            $entity,
            $revision->field,
            $baseHash,
            (string) $revision->value,
            __('Reverted to :date', ['date' => $revision->created_at->format('d F H:i')]),
            $user,
        );
    }

    /**
     * Restores only the fields touched by one save.
     *
     * It checks every hash before any write. The undo is atomic and creates one
     * new save point.
     *
     * @param  Collection<int, Revision>  $group  Rows with one save ID.
     * @param  array<string, string>  $baseHashes  Field hashes shown by the page.
     * @return list<string>
     *
     * @throws RevisionConflictException When any stored value changed.
     */
    public function revertSave(Model $entity, Collection $group, array $baseHashes, User $user): array
    {
        // The earliest row's predecessor holds the value from before the save.
        $rows = $group->sortBy([['created_at', 'asc'], ['id', 'asc']])->unique('field');

        return DB::transaction(function () use ($entity, $rows, $baseHashes, $user): array {
            foreach ($rows as $row) {
                // A missing hash cannot authorize a blind write.
                $this->assertUnchanged($entity, $row->field, $baseHashes[$row->field] ?? '');
            }

            $this->recorder->startNewSave($entity);

            $label = __('Undid the save of :date', [
                'date' => $rows->first()->created_at->format('d F H:i'),
            ]);

            return $rows
                ->map(fn (Revision $row): string => $this->restore(
                    $entity,
                    $row->field,
                    $baseHashes[$row->field] ?? '',
                    // No predecessor means the field was empty before the save.
                    (string) ($this->predecessorOf($entity, $row)?->value ?? ''),
                    $label,
                    $user,
                ))
                ->values()
                ->all();
        });
    }

    /**
     * Performs the in-memory preflight check before validation or a transaction.
     *
     * @throws RevisionConflictException
     */
    public function assertUnchanged(Model $entity, string $field, string $baseHash): void
    {
        $currentValue = (string) ($entity->getAttribute($field) ?? '');

        if ($baseHash !== hash('sha256', $currentValue)) {
            throw RevisionConflictException::valueChangedElsewhere($field);
        }
    }

    /**
     * Locks the row and checks its raw stored value inside the transaction.
     * This closes the race between the preflight check and the write.
     *
     * @throws RevisionConflictException
     */
    private function assertStillUnchanged(Model $entity, string $field, string $baseHash): void
    {
        $storedValue = (string) ($entity->newQuery()
            ->whereKey($entity->getKey())
            ->lockForUpdate()
            ->value($field) ?? '');

        if ($baseHash !== hash('sha256', $storedValue)) {
            throw RevisionConflictException::valueChangedElsewhere($field);
        }
    }

    /**
     * Validates, restores, and records one old value in one transaction.
     *
     * Current rules apply to the old value. The recorded revision uses the value
     * after model mutators run. The locked hash check prevents concurrent overwrite.
     */
    private function restore(Model $entity, string $field, string $baseHash, string $value, string $label, User $user): string
    {
        $slug = AutosavableFields::slugFor($entity::class);

        // Validate first and use the field name in any user-facing error.
        Validator::make(
            ['value' => $value],
            ['value' => AutosavableFields::validationRule($slug, $field)],
            [],
            ['value' => Str::headline($field)],
        )->validate();

        return DB::transaction(function () use ($entity, $field, $baseHash, $value, $label, $user): string {
            $this->assertStillUnchanged($entity, $field, $baseHash);

            $entity->{$field} = $value;
            $entity->save(); // Mutators run here, e.g. SanitizesRichHtml for rich fields.

            // The assigned in-memory value already includes set mutators.
            $storedValue = (string) ($entity->getAttribute($field) ?? '');

            $this->recorder->record($entity, $field, $storedValue, $user, RevisionOrigin::Revert, $label);

            return $field;
        });
    }

    /** Returns the preceding revision by creation time and ID. */
    private function predecessorOf(Model $entity, Revision $row): ?Revision
    {
        return $entity->revisions()
            ->where('field', $row->field)
            ->where(fn (Builder $query) => $query
                ->where('created_at', '<', $row->created_at)
                ->orWhere(fn (Builder $tie) => $tie
                    ->where('created_at', $row->created_at)
                    ->where('id', '<', $row->id)))
            ->latest('created_at')
            ->latest('id')
            ->first();
    }
}
