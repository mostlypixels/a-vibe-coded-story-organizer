<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            // No FK on the target: it is polymorphic, like revisions.revisionable_id.
            $table->string('notable_type');
            $table->unsignedBigInteger('notable_id');

            $table->unique(['note_id', 'notable_type', 'notable_id']);
            $table->index(['notable_type', 'notable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notables');
    }
};
