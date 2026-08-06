<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `include_chapter_covers` toggle — full-page chapter cover images in the
 * epub. The original create_publication_settings_table migration missed it, the same
 * way it missed `section_order`. A separate migration keeps that one untouched.
 *
 * It defaults to false. A full-page chapter cover is a new rendering, and every
 * new rendering toggle defaults off, so a default project's export does not
 * change.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('publication_settings', function (Blueprint $table) {
            $table->boolean('include_chapter_covers')->default(false)->after('include_project_cover');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publication_settings', function (Blueprint $table) {
            $table->dropColumn('include_chapter_covers');
        });
    }
};
