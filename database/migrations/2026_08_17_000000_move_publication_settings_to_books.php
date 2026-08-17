<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `PublicationSetting` moves onto the book (expanded/data-model.md -> *Changed
 * foreign keys*): the unique index moves from `project_id` to `book_id`, and
 * `include_project_cover` renames with it.
 *
 * The only data is the seed: run `php artisan migrate:fresh --seed` after
 * this (see `add_book_id_to_acts_table` for the same destructive posture —
 * `book_id` is NOT NULL with no rule for which book an existing row belongs
 * to).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('publication_settings')->delete();

        Schema::table('publication_settings', function (Blueprint $table) {
            $table->renameColumn('include_project_cover', 'include_book_cover');
        });

        Schema::table('publication_settings', function (Blueprint $table) {
            // dropConstrainedForeignId() only drops the FK and the column — the
            // separate unique index `->unique()` added still points at the column,
            // and SQLite's rebuild-the-table DROP COLUMN chokes on a dangling
            // index reference unless it goes first.
            $table->dropUnique(['project_id']);
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('publication_settings', function (Blueprint $table) {
            $table->foreignId('book_id')->unique()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('publication_settings')->delete();

        Schema::table('publication_settings', function (Blueprint $table) {
            $table->renameColumn('include_book_cover', 'include_project_cover');
        });

        Schema::table('publication_settings', function (Blueprint $table) {
            $table->dropUnique(['book_id']);
            $table->dropConstrainedForeignId('book_id');
        });

        Schema::table('publication_settings', function (Blueprint $table) {
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
        });
    }
};
