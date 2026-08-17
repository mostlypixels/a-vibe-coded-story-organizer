<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `projects.last_book_id` — the book the writer was last in, per project. It
 * returns the writer to that book after a Codex or Tools detour.
 *
 * Its own migration because `books.project_id` already points the other way: the
 * two tables reference each other, so `books` must exist first.
 *
 * Nullable + `nullOnDelete()`, and NOT fillable: only the route-tracking
 * middleware writes it, the same rule `users.active_project_id` follows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('last_book_id')->nullable()->after('overview_render_mode')->constrained('books')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_book_id');
        });
    }
};
