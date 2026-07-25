<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Groups revision rows into *save points* and gives every row the two
 * pre-computed columns the history list renders from.
 *
 * A save point is "one Save / one autosave burst": the set of per-field rows
 * written together. Storage stays per field — this only adds the grouping key
 * (`save_id`) that the history, compare and revert screens address instead of
 * addressing individual field rows.
 *
 * `summary_html` and `change_count` exist so a list page never computes a diff
 * to render: a diff between two immutable revisions is a constant, so it is
 * computed once at write time (App\Services\RevisionRecorder) and stored. List
 * queries can then avoid hydrating `revisions.value` entirely.
 *
 * > [!WARNING]
 * > This migration **deletes every existing revision row** before adding the
 * > columns. See the class docblock of down() and the note below.
 *
 * Why delete rather than backfill: a null grouping key poisons every read path
 * (`GROUP BY save_id` collapses all legacy rows into a single bogus group, and
 * the `COALESCE(save_id, 'row:' || id)` workaround is not portable across the
 * five supported database engines), while a per-row ULID backfill would only
 * buy an era of rows that have grouping but no summaries. Deleting leaves a
 * table where every row provably came from the new write path: no null
 * `save_id` branch, no summary-less era.
 *
 * The cost is a non-event: the project is pre-V1 and the only data in existence
 * is the Melusine demo seed. Nothing else breaks either — `revisions` has no
 * inbound foreign keys, and RevisionRecorder::ensureBaseline() re-seeds a
 * `baseline` row from the entity's live value the first time each field is
 * written again, so history restarts rather than staying empty.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Before the schema change, not after: the new columns are null-free in
        // fact, and the only way to guarantee that without a backfill is to
        // start from an empty table.
        DB::table('revisions')->delete();

        Schema::table('revisions', function (Blueprint $table) {
            // The save-point key: every row written by the same Save (or the
            // same autosave burst) shares one ULID. ULID rather than UUID —
            // fixed 26 chars, and lexicographically sortable by creation time,
            // which gives ordering a free deterministic tiebreaker.
            //
            // Nullable in DDL even though it is never null in practice: SQLite
            // cannot ADD COLUMN … NOT NULL without a default, and recreating
            // the whole table here would duplicate its definition across two
            // migration files where a reader has to work out which one wins.
            // The invariant is guarded by a test instead of a constraint.
            $table->char('save_id', 26)->nullable()->after('field');

            // Rendered diff fragment for this row, computed once at write time
            // (App\Services\RevisionSummarizer) so the history list never
            // diffs anything at read time. Contains the diff layer's own
            // <ins>/<del> markup — already escaped and allow-listed by
            // App\Services\DiffHtmlRenderer, never re-sanitised on the way out.
            $table->text('summary_html')->nullable();

            // How many change hunks this row's diff has, so the list can say
            // "+3 more changes" without re-diffing to count them.
            $table->unsignedInteger('change_count')->nullable();

            // The history page's query: every row of one entity, grouped by
            // save point.
            $table->index(['revisionable_type', 'revisionable_id', 'save_id'], 'revisions_entity_save_idx');

            // Whole-save operations (compare, "undo this save") address a
            // save_id on its own, without the entity in hand.
            $table->index('save_id', 'revisions_save_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * > [!WARNING]
     * > This rollback **cannot restore the revision rows up() deleted**. It
     * > drops the grouping and summary columns and nothing else; the history
     * > that existed before up() ran is gone for good. Rolling back leaves a
     * > table in the pre-save-point shape but empty (plus whatever the new
     * > write path wrote in the meantime).
     */
    public function down(): void
    {
        Schema::table('revisions', function (Blueprint $table) {
            // Indexes first: SQLite refuses to drop a column that an index
            // still references.
            $table->dropIndex('revisions_entity_save_idx');
            $table->dropIndex('revisions_save_idx');

            $table->dropColumn(['save_id', 'summary_html', 'change_count']);
        });
    }
};
