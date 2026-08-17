<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `App\Enums\StoryOverviewMode::Book` renamed to `Whole` (`'book'` -> `'whole'`)
 * so "book" names only the new `Book` model everywhere in the app. Rewrites
 * every stored `projects.overview_render_mode` value to match; a surviving
 * `'book'` row would fail to cast to the enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('projects')->where('overview_render_mode', 'book')->update([
            'overview_render_mode' => 'whole',
        ]);
    }

    public function down(): void
    {
        DB::table('projects')->where('overview_render_mode', 'whole')->update([
            'overview_render_mode' => 'book',
        ]);
    }
};
