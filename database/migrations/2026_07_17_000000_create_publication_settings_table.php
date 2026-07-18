<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('publication_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();

            // Cover and metadata toggles (default true to preserve v1 behaviour)
            $table->boolean('include_project_cover')->default(true);
            $table->boolean('include_author')->default(true);
            $table->boolean('include_publisher')->default(true);
            $table->boolean('include_rights')->default(true);
            $table->boolean('include_isbn')->default(true);

            // New rendering toggles (default false)
            $table->boolean('include_scene_titles')->default(false);
            $table->boolean('include_act_descriptions')->default(false);
            $table->boolean('include_chapter_descriptions')->default(false);
            $table->boolean('include_scene_descriptions')->default(false);

            // Front/back matter toggles (default false)
            $table->boolean('include_dedication')->default(false);
            $table->boolean('include_acknowledgements')->default(false);
            $table->boolean('include_preface')->default(false);
            $table->boolean('include_postface')->default(false);

            // Format and style choices (default to v1 behaviour)
            $table->string('chapter_title_format')->default('chapter_number_title');
            $table->string('table_of_contents_depth')->default('chapters');
            $table->string('divider_type')->default('horizontal_rule');

            // Appendix options (default false/empty)
            $table->boolean('include_codex_appendix')->default(false);
            $table->json('appendix_entry_types')->default('[]');
            $table->boolean('appendix_include_images')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_settings');
    }
};
