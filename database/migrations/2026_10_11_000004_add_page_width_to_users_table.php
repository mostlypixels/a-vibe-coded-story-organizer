<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.page_width` — the share of the window, in percent, the page content fills.
 *
 * Nullable, no default: `null` means "never chosen". `App\Support\PageWidth::resolve()`
 * turns that into `config('page_width.default')`, so no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('page_width')->nullable()->after('page_size');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('page_width');
        });
    }
};
