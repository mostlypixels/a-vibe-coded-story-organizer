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
        // Singleton table: the whole application shares exactly one import policy
        // (max archive size + default run mode). Like crawler_settings it has no
        // project_id / user_id — it is a global admin setting. Always read it
        // through ImportSetting::current(), never create a second row.
        Schema::create('import_settings', function (Blueprint $table) {
            $table->id();

            // The largest accepted import archive, in kilobytes. This column
            // default is a backstop for direct inserts; the documented source of
            // truth for the lazy-create path is
            // config('import.default_max_archive_kilobytes'). Keep the two equal.
            $table->integer('max_archive_kilobytes')->default(204800);

            // Whether imports run via the queued ProjectImportJob by default.
            // Synchronous (false) is the safe default; queued is opt-in.
            $table->boolean('run_in_background')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_settings');
    }
};
