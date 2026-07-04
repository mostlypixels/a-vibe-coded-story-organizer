<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('codex_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('codex_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('codex_attribute_id')->constrained()->cascadeOnDelete();
            // The event this value takes effect from (the step-function anchor).
            $table->foreignId('start_event_id')->constrained('events')->cascadeOnDelete();
            $table->text('value');
            $table->timestamps();

            // Backstop only: the store endpoint upserts on this key rather than
            // rejecting duplicates (see attribute-timeline.md).
            $table->unique(['codex_entry_id', 'codex_attribute_id', 'start_event_id']);
            // Every timeline query loads one attribute's periods for one entry.
            $table->index(['codex_entry_id', 'codex_attribute_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codex_attribute_values');
    }
};
