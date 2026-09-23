<?php

use App\Support\AutosavableFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Delete revisions whose entity row is gone.
 *
 * Before HasRevisions deleted them with the entity, they stayed forever:
 * prune always keeps the newest row, and nothing can show or revert them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (AutosavableFields::slugs() as $slug) {
            $model = new (AutosavableFields::modelFor($slug));

            DB::table('revisions')
                ->where('revisionable_type', $model->getMorphClass())
                ->whereNotIn('revisionable_id', DB::table($model->getTable())->select('id'))
                ->delete();
        }
    }

    /**
     * No-op: the dropped rows are pre-V1 demo data, not recoverable history.
     */
    public function down(): void {}
};
