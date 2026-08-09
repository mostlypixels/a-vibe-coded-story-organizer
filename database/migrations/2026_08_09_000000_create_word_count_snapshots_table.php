<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `word_count_snapshots` — one row per project per writer-day, holding that
 * day's cumulative word count (word-count-goals spec, `data-model.md` →
 * "New table: word_count_snapshots").
 *
 * `recorded_on` is a date, not a timestamp: the row means "the total at the
 * end of this writer-day". The unique index on (project_id, recorded_on) is
 * also the range index a month query uses, so there is no second index.
 *
 * There is no backfill. The table starts empty; a project with no rows yet
 * had a total of 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_count_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_on');
            $table->unsignedInteger('word_count')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_count_snapshots');
    }
};
