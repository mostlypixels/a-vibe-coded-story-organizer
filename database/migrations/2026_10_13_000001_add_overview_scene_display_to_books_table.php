<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `books.overview_scene_display` — the `StoryOverviewSceneDisplay` value of the Story overview. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('overview_scene_display')->default('description')->after('overview_render_mode');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('overview_scene_display');
        });
    }
};
