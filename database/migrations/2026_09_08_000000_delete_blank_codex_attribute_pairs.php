<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Delete every (entry, attribute) pair whose values are all blank.
 *
 * The old create form wrote a blank row for every attribute of an entry's type, so row
 * presence said nothing. Now presence means "attached to this entry", and a pair with
 * nothing but blank values is leftover from the old form, not a real attachment.
 *
 * A pair with a blank Start period but a filled later period stays: that is a real
 * "unknown until X" timeline, not leftovers.
 */
return new class extends Migration
{
    public function up(): void
    {
        $blankPairs = DB::table('codex_attribute_values')
            ->selectRaw('codex_entry_id, codex_attribute_id')
            ->groupBy('codex_entry_id', 'codex_attribute_id')
            ->havingRaw('MAX(LENGTH(TRIM(value))) = 0')
            ->get();

        foreach ($blankPairs as $pair) {
            DB::table('codex_attribute_values')
                ->where('codex_entry_id', $pair->codex_entry_id)
                ->where('codex_attribute_id', $pair->codex_attribute_id)
                ->delete();
        }
    }

    /**
     * No-op: the dropped rows are pre-V1 demo data, not recoverable history.
     */
    public function down(): void {}
};
