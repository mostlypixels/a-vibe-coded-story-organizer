<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `include_chapter_covers` toggle (task 12: full-page chapter cover images in the
 * epub), missed by the original create_publication_settings_table migration (task 01) the
 * same way `section_order` was — filled in here rather than editing the already-implemented
 * migration, since this task is the first real consumer. Defaults false: chapter covers are
 * a brand-new rendering, and overview decision #3 requires every new rendering toggle to
 * default off so a default project's export is unchanged.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('publication_settings', function (Blueprint $table) {
            $table->boolean('include_chapter_covers')->default(false)->after('include_project_cover');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publication_settings', function (Blueprint $table) {
            $table->dropColumn('include_chapter_covers');
        });
    }
};
