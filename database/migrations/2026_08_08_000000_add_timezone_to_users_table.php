<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.timezone` — the writer's local day, used to date word-count snapshots.
 *
 * Nullable, no default. `null` means "follow config('app.timezone')" — copy the
 * `users.theme_slug` migration's reasoning verbatim: do NOT write the default
 * into the column, or every existing user is frozen onto today's default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone')->nullable()->after('theme_slug');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
