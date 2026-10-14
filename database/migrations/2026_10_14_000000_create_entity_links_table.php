<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // No FK on the source or the target: both are polymorphic, like notables.notable_id.
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('source_field');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');

            $table->unique(['source_type', 'source_id', 'source_field', 'target_type', 'target_id']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_links');
    }
};
