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
        // One row per import attempt. This is the checkpoint record that makes a
        // synchronous request-timeout or a queued-worker crash recoverable instead
        // of leaving a silent orphan: `phase` + `id_maps` capture progress after
        // each phase commits, so a resume never replays completed phases.
        Schema::create('imports', function (Blueprint $table) {
            $table->id();

            // The importing user — same ownership rule as everywhere else. If the
            // user is deleted, their in-flight imports go with them.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The Project being built, set as soon as phase 1 (Project) commits.
            // nullOnDelete (NOT cascade): if that project is deleted by some other
            // path before this row is cleaned up, the Import row must survive as an
            // orphaned/failed record the user can still see and discard — never
            // vanish silently. Nullable because there is no project yet at
            // phase = pending.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            // The uploaded zip's path on a private disk. Kept until the import
            // completes or is discarded (not deleted immediately) precisely so a
            // crash can resume from it.
            $table->string('archive_path');

            // The original uploaded filename, for the in-progress-imports list's
            // display (task 08). archive_path is a generated uuid, meaningless to
            // show a user.
            $table->string('archive_original_name');

            // The last successfully completed ImportPhase (cast to the enum on the
            // model). Resuming starts at the next phase.
            $table->string('phase');

            // The accumulated id-remapping arrays ($actIdMap/$eventIdMap/...),
            // persisted after each phase commits so a resume doesn't replay earlier
            // phases to rebuild them. Nullable: empty before phase 1.
            $table->json('id_maps')->nullable();

            // Whether this import is running via the queued ProjectImportJob (true)
            // or inline (false) — drives which feedback the Import tab shows.
            $table->boolean('queued')->default(false);

            // A safe-to-display failure message from whichever phase failed. Never a
            // raw stack trace. Nullable — set only on failure.
            $table->string('failure_message')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
