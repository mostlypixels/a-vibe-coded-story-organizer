<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.theme_slug` — the per-user theme preference (theme-switcher spec,
 * `.specs/planned/2026-08/theme-switcher`).
 *
 * Nullable, no default. `null` means "follow config('themes.default')", so
 * changing the site-wide default still reaches every user who never picked a
 * preset. Do NOT copy the default into the column — that would freeze every
 * existing user onto today's default forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme_slug')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme_slug');
        });
    }
};
