<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inception and termination are single event links, not attribute periods
 * (expanded/data-model.md → *Why FK columns, not reserved attributes*). Columns
 * are nullable and `nullOnDelete`: a deleted event clears the link but the
 * entry survives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codex_entries', function (Blueprint $table) {
            $table->foreignId('inception_event_id')->nullable()->after('description')
                ->constrained('events')->nullOnDelete();
            $table->foreignId('termination_event_id')->nullable()->after('inception_event_id')
                ->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('codex_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inception_event_id');
            $table->dropConstrainedForeignId('termination_event_id');
        });
    }
};
