<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.ui_leading` — the line spacing of the interface, beside the manuscript
 * one the previous migration added.
 *
 * Nullable, no default, for the same reason as the columns beside it: `null`
 * means "follow `config('fonts.default_ui_leading')`".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ui_leading')->nullable()->after('manuscript_leading');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ui_leading');
        });
    }
};
