<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `projects.daily_word_goal` and `total_word_goal` — the writer's open-ended
 * targets, read by the Progress page and the dashboard card.
 *
 * Nullable, no default. `null` means "no goal set": the chart draws no goal
 * line and the readouts drop the "of X words" half entirely. A default of 0
 * would draw a goal line at zero on every project ever created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('daily_word_goal')->nullable()->after('postface');
            $table->unsignedInteger('total_word_goal')->nullable()->after('daily_word_goal');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['daily_word_goal', 'total_word_goal']);
        });
    }
};
