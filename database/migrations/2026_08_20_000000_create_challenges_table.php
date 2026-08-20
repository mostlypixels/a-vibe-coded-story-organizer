<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `challenges` — a window plus a target (word-count-challenges spec,
 * `data-model.md` → "New table: challenges").
 *
 * No progress columns. Words so far, par, and the verdict all fall out of
 * `word_count_snapshots` at read time, so an edit to the target or window
 * re-scores the past instead of going stale.
 *
 * `starts_on` / `ends_on` are dates, not timestamps, matching
 * `word_count_snapshots.recorded_on`. `ends_on` is nullable: a monthly
 * challenge runs until deleted, or until an optional stop date. The Form
 * Requests require it for a fixed challenge; the schema does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('recurrence')->default('none');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('target_words');
            $table->timestamps();

            $table->index(['project_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
