<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.page_size` — how many rows a user's entity lists paginate by.
 *
 * Nullable, no default, for the same reason as `theme_slug` and `ui_leading`
 * beside it: `null` means "never chosen". `App\Support\PageSize::resolve()`
 * turns that into `config('pagination.default')`, so no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('page_size')->nullable()->after('ui_leading');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('page_size');
        });
    }
};
