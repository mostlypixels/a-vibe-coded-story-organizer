<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();

            // Polymorphic target field this revision snapshots. Deliberately no
            // FK on revisionable_type/revisionable_id — standard polymorphic
            // shape, matches the existing scene_codex_entry pivot convention of
            // not enforcing referential integrity on polymorphic columns.
            $table->string('revisionable_type');
            $table->unsignedBigInteger('revisionable_id');
            $table->string('field');

            // longText from the start: this is a brand-new column with no
            // legacy MySQL text() data to widen (unlike the 14 live columns
            // task 2 migrates).
            $table->longText('value');

            // Populated from strlen($value) on every write (task 4). Lets the
            // storage panel and purge preview do a plain SUM(size_bytes)
            // group-by-origin query, portable across all five supported
            // database engines without LENGTH()/octet_length()/DATALENGTH()
            // differences.
            $table->unsignedInteger('size_bytes');

            // Real FK, not polymorphic: deleting a Project cascades to its
            // acts/chapters/scenes at the DB level without firing Eloquent
            // events, so a `deleting` hook on Revision would silently never
            // run. This FK is the only mechanism that reliably sweeps the
            // bulk-delete case. For Project itself, project_id equals its own
            // id, set explicitly (a self-referential FK still needs a value).
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();

            $table->string('label')->nullable();

            // App\Enums\RevisionOrigin, string-backed. Only "automatic"
            // revisions are ever prunable.
            $table->string('origin');

            // No updated_at: a revision is immutable once its coalescing
            // window closes. The coalescing overwrite happens via a plain
            // UPDATE against the still-open row (RevisionRecorder, task 4),
            // not an Eloquent touch(). useCurrent() here is only a backstop
            // for direct inserts — every application write sets created_at
            // explicitly.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['revisionable_type', 'revisionable_id', 'field', 'created_at'], 'revisions_entity_field_idx');
            $table->index('project_id');
            $table->index('label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
