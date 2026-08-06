<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

/**
 * Authorization for acting on an in-progress {@see Import} record.
 *
 * This is a NORMAL owned-resource policy: by the time an Import row exists it
 * has an owner (the importing user), so resume/discard walk to that owner —
 * unlike the initial POST that starts an import, which uses the
 * any-authenticated-user exception (there is no project yet to walk up to).
 */
class ImportPolicy
{
    public function resume(User $user, Import $import): bool
    {
        return $user->id === $import->user_id;
    }

    public function discard(User $user, Import $import): bool
    {
        return $user->id === $import->user_id;
    }
}
