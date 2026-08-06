<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the sortable front/back-matter section order. The original
 * create_publication_settings_table migration missed it, and a separate
 * migration keeps that one untouched. The Export-ebook config form is the
 * first consumer of this column.
 *
 * `title` is always first and pinned in the UI; `toc` and `body` are the
 * built-in slots that are now reorderable alongside the Markdown matter pages.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('publication_settings', function (Blueprint $table) {
            $table->json('section_order')
                ->default('["title","dedication","acknowledgements","preface","toc","body","postface","appendix"]')
                ->after('include_postface');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publication_settings', function (Blueprint $table) {
            $table->dropColumn('section_order');
        });
    }
};
