<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Location codex entries where an event happens.
        Schema::create('codex_entry_event', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('codex_entry_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_id', 'codex_entry_id']);
            // The codex entry page lists the events at a location.
            $table->index('codex_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codex_entry_event');
    }
};
