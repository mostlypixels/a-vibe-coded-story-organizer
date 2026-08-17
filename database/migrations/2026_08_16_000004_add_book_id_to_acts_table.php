<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The manuscript hangs off a book, so `acts` points at `books` instead of
 * `projects` (expanded/data-model.md → *Changed foreign keys*).
 *
 * > [!WARNING]
 * > `project_id` is dropped, not kept beside `book_id`. Two paths to the same
 * > owner drift the first time an act moves between books. An act reaches its
 * > project through `$act->book->project`.
 *
 * The manuscript rows are deleted first. `book_id` is NOT NULL with no sensible
 * default, and there is no rule that says which book an existing act belongs to.
 * The only data is the seed: run `php artisan migrate:fresh --seed` after this.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('acts')->delete();

        Schema::table('acts', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('acts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }

    public function down(): void
    {
        DB::table('acts')->delete();

        Schema::table('acts', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('acts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('book_id');
        });
    }
};
