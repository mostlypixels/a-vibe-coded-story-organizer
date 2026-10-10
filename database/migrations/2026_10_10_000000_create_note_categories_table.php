<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // A safety net only: NoteCategoryDeleter moves the children up first.
            $table->foreignId('parent_id')->nullable()->constrained('note_categories')->nullOnDelete();
            // Unique among siblings, checked in the request: SQLite treats null parents as distinct.
            $table->string('name');
            $table->timestamps();

            $table->index(['project_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_categories');
    }
};
