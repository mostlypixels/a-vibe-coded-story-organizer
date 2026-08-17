<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Delete the project revisions for the fields that belong to a book now.
 *
 * `AutosavableFields::REGISTRY` no longer registers `project.dedication`,
 * `acknowledgements`, `preface`, `postface` or `rights`, and an unregistered
 * slug+field pair has no edit route and no character cap. The rows are history
 * for a field the project no longer autosaves, so they go with it.
 */
return new class extends Migration
{
    public const MOVED_FIELDS = [
        'dedication',
        'acknowledgements',
        'preface',
        'postface',
        'rights',
    ];

    public function up(): void
    {
        DB::table('revisions')
            ->where('revisionable_type', Project::class)
            ->whereIn('field', self::MOVED_FIELDS)
            ->delete();
    }

    /**
     * No-op: deleted history is not recoverable.
     */
    public function down(): void {}
};
