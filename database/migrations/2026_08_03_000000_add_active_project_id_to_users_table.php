<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.active_project_id` — the last project page the user successfully loaded
 * (active-project spec, `.specs/planned/2026-08/active-project`). Drives the nav
 * fallback everywhere, including pages with no project in the URL.
 *
 * Nullable + `nullOnDelete()`: deleting the active project unassigns it rather than
 * cascading, same shape as `scenes.event_id`
 * (`2026_07_04_000000_add_event_id_to_scenes_table.php`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_project_id')->nullable()->after('theme_slug')->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_project_id');
        });
    }
};
