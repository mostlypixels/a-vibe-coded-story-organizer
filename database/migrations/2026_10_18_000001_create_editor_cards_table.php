<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per user, scene and card on the Write page. Exactly one of
        // codex_entry_id and note_id is set. SQLite and MySQL treat NULLs as distinct
        // in a unique index, so EditorCard::saveState() enforces uniqueness too.
        Schema::create('editor_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->foreignId('codex_entry_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('note_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('state');
            $table->timestamps();

            $table->unique(['user_id', 'scene_id', 'codex_entry_id', 'note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editor_cards');
    }
};
