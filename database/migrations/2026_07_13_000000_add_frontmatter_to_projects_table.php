<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the four front-/back-matter Markdown columns projects carry so an
     * epub export can render a dedication, acknowledgements, preface, and
     * postface. All four are nullable `text` columns, placed after
     * `cover_image` (the last of the existing epub-metadata columns).
     *
     * These stay RAW Markdown, exactly like `Scene.contents` — never rich
     * HTML, never routed through SanitizesRichHtml. They render through
     * EpubExporter's own SmartPunct CommonMark converter (task 11), never
     * Scene::renderedContents.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('dedication')->nullable()->after('cover_image');
            $table->text('acknowledgements')->nullable()->after('dedication');
            $table->text('preface')->nullable()->after('acknowledgements');
            $table->text('postface')->nullable()->after('preface');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'dedication',
                'acknowledgements',
                'preface',
                'postface',
            ]);
        });
    }
};
