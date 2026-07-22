<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widens every column `AutosavableFields` will register (see the
     * autosave-with-revisions spec) from `text()` (65,535-byte cap on
     * MySQL/MariaDB) to `longText()` (effectively unlimited). This is a
     * real behavioral change only on MySQL/MariaDB — pgsql/sqlite/sqlsrv
     * already map `text()`/`longText()` identically, so the migration is a
     * no-op there but still runs cleanly on all five engines.
     *
     * Purely a column-type change: no new columns, no data migration.
     * `revisions.value` was already created as `longText()` directly, so it
     * is not touched here.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->longText('description')->nullable()->change();
            $table->longText('dedication')->nullable()->change();
            $table->longText('acknowledgements')->nullable()->change();
            $table->longText('preface')->nullable()->change();
            $table->longText('postface')->nullable()->change();
            $table->longText('rights')->nullable()->change();
        });

        Schema::table('acts', function (Blueprint $table) {
            $table->longText('description')->nullable()->change();
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->longText('description')->nullable()->change();
        });

        Schema::table('plotlines', function (Blueprint $table) {
            $table->longText('description')->nullable()->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->longText('description')->nullable()->change();
        });

        Schema::table('scenes', function (Blueprint $table) {
            $table->longText('description')->nullable()->change();
            $table->longText('notes')->nullable()->change();
            $table->longText('contents')->nullable()->change();
        });

        Schema::table('codex_entries', function (Blueprint $table) {
            $table->longText('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Reverts each column back to `text()`. On MySQL/MariaDB this would
     * truncate any value over 65,535 bytes written while `longText()` was in
     * effect — acceptable for a `down()` used only in local development.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->text('dedication')->nullable()->change();
            $table->text('acknowledgements')->nullable()->change();
            $table->text('preface')->nullable()->change();
            $table->text('postface')->nullable()->change();
            $table->text('rights')->nullable()->change();
        });

        Schema::table('acts', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        Schema::table('plotlines', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        Schema::table('scenes', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->text('notes')->nullable()->change();
            $table->text('contents')->nullable()->change();
        });

        Schema::table('codex_entries', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }
};
