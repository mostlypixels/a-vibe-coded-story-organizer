<?php

namespace App\Rules;

use App\Services\RevisionRecorder;
use App\Support\AutosavableFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/**
 * An "after" validation hook for a full-form save of an entity with autosaved fields.
 *
 * The edit form sends `base_hashes[field]`: the hash of the stored text that the page
 * last saw. A different stored hash means another tab or device saved newer text. The
 * save then fails, so it cannot overwrite that text. The edit page shows the text of
 * the writer again, with the conflict choice open.
 *
 * It is a hook, not a rule on `base_hashes`, so the hashes stay out of `validated()`.
 * The savers fill models from `validated()`.
 *
 * > [!WARNING]
 * > This hook runs before the save transaction, so an autosave can still land after it.
 * > {@see RevisionRecorder::saveWithManualCheckpoint()} repeats the check on the locked row.
 */
final class NoAutosaveConflict
{
    /** @param  array<mixed>  $baseHashes */
    public function __construct(
        private readonly Model $model,
        private readonly array $baseHashes,
    ) {}

    public function __invoke(Validator $validator): void
    {
        foreach (self::errors($this->model, $this->baseHashes) as $key => $message) {
            $validator->errors()->add($key, $message);
        }
    }

    /**
     * One error per autosaved field whose stored text is newer than the page.
     *
     * @param  array<mixed>  $baseHashes
     * @return array<string, string> Keyed `base_hashes.<field>`.
     */
    public static function errors(Model $model, array $baseHashes): array
    {
        $errors = [];

        foreach (AutosavableFields::currentHashes($model) as $field => $currentHash) {
            $sent = $baseHashes[$field] ?? null;

            // A form without the hash (an old page, no JavaScript) saves as before.
            if (is_string($sent) && $sent !== $currentHash) {
                $errors["base_hashes.$field"] = __('This text was changed in another tab or on another device.');
            }
        }

        return $errors;
    }
}
