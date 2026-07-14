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
        // Derived cache: which codex entries are referenced (by name/alias, as a whole
        // word) in a scene's contents. A pure link table with no identity of its own —
        // recomputed wholesale on each save, never partially patched. Mirrors the
        // codex_entry_tag / event_scene pivot convention: no id, no timestamps.
        Schema::create('scene_codex_entry', function (Blueprint $table) {
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->foreignId('codex_entry_id')->constrained()->cascadeOnDelete();
            $table->primary(['scene_id', 'codex_entry_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scene_codex_entry');
    }
};
