<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.ui_font`, `manuscript_font`, `ui_scale`, `manuscript_scale`,
 * `manuscript_leading` — the writer's font and scale preferences.
 *
 * Nullable, no default. `null` means "follow the `config('fonts.*')` default" —
 * copy the `users.theme_slug` migration's reasoning verbatim: do NOT write a
 * default into a column, or every existing user is frozen onto today's default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ui_font')->nullable()->after('theme_slug');
            $table->string('manuscript_font')->nullable()->after('ui_font');
            $table->string('ui_scale')->nullable()->after('manuscript_font');
            $table->string('manuscript_scale')->nullable()->after('ui_scale');
            $table->string('manuscript_leading')->nullable()->after('manuscript_scale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ui_font',
                'manuscript_font',
                'ui_scale',
                'manuscript_scale',
                'manuscript_leading',
            ]);
        });
    }
};
