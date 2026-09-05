<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.locale` — the writer's date-format preference.
 *
 * Nullable, no default. `null` means "follow config('locales.default')" — same
 * reasoning as `users.theme_slug` and `users.timezone`. Do NOT write the
 * default into the column, or every existing user is frozen onto today's default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
