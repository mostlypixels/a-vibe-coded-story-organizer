<?php

namespace App\Http\Requests\Concerns;

use App\Rules\NoAutosaveConflict;
use App\Services\RevisionRecorder;

/**
 * The `base_hashes` of an edit form with autosaved fields. {@see NoAutosaveConflict}
 * checks them during validation, and {@see RevisionRecorder::saveWithManualCheckpoint()}
 * checks them again inside the save.
 */
trait ReadsBaseHashes
{
    /** @return array<mixed> */
    public function baseHashes(): array
    {
        return (array) $this->input('base_hashes', []);
    }
}
