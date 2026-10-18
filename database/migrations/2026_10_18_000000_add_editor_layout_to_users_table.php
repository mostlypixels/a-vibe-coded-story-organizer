<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.editor_layout` — panel widths and collapse state of the Write page.
 *
 * Nullable JSON: `null` means "never chosen". `App\Support\EditorLayout::resolve()`
 * fills the defaults, so no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('editor_layout')->nullable()->after('page_width');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('editor_layout');
        });
    }
};
