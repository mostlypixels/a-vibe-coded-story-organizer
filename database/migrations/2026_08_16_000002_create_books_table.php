<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `books` — a project holds one or more books, and the manuscript hangs off the
 * book (expanded/data-model.md).
 *
 * `name` is nullable on purpose: null means "no name of my own", and
 * `Book::displayName()` falls back to the project name. A one-book project then
 * holds one name, not two that drift apart.
 *
 * The publication columns (`language` through `postface`) and
 * `overview_render_mode` are the same shape as the `projects` columns they
 * replace, so a later move is a copy and a drop.
 *
 * The long-text columns are `longText()` from the start — the reason
 * `2026_07_22_000001_widen_long_text_columns_to_long_text.php` widened their
 * `projects` twins: `text()` caps at 65,535 bytes on MySQL/MariaDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->longText('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('cover_image')->nullable();
            $table->string('language', 10)->default('en');
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->longText('rights')->nullable();
            $table->string('isbn', 17)->nullable();
            $table->longText('dedication')->nullable();
            $table->longText('acknowledgements')->nullable();
            $table->longText('preface')->nullable();
            $table->longText('postface')->nullable();
            $table->string('overview_render_mode')->default('chapter');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
