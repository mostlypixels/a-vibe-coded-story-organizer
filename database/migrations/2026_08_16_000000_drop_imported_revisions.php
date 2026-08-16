<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Delete every revision that an import replayed.
 *
 * An archive no longer carries revision history, so `RevisionOrigin` has no
 * `import` case. The `revisions.origin` column is a plain string cast to that
 * enum, so a surviving `'import'` row throws when the model hydrates it.
 *
 * > [!WARNING]
 * > The rows are deleted, never relabeled to `manual`. They are the edit trail
 * > of a different install, and a "Saved" badge would claim a save that this
 * > install never made.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('revisions')->where('origin', 'import')->delete();
    }

    /**
     * No-op: deleted history is not recoverable.
     */
    public function down(): void {}
};
