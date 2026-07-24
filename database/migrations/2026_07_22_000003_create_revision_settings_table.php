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
        // Singleton table: the whole application shares exactly one revision
        // retention policy (how many days an `automatic`, unlabeled revision
        // survives before Revision::prunable() considers it eligible for the
        // daily model:prune sweep). Like import_settings it has no project_id /
        // user_id — it is a global admin setting. Always read it through
        // RevisionSetting::current(), never create a second row.
        Schema::create('revision_settings', function (Blueprint $table) {
            $table->id();

            // This column default is a backstop for direct inserts; the
            // documented source of truth for the lazy-create path is
            // config('revisions.retention_days'). Keep the two equal
            // (same "duplicated by design" pattern as import_settings).
            $table->unsignedInteger('retention_days')->default(90);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revision_settings');
    }
};
