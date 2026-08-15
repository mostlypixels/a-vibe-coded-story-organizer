<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `projects.overview_render_mode` — which layout the Story overview uses,
 * see App\Enums\StoryOverviewMode.
 *
 * Defaults to `chapter` so existing rows paginate like new ones, with no
 * backfill step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('overview_render_mode')->default('chapter')->after('total_word_goal');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('overview_render_mode');
        });
    }
};
