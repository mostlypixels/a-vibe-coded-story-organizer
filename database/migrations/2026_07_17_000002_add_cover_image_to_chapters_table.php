<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chapter cover image (epub-configuration, task 07): a plain nullable path column
 * on `chapters`, mirroring `projects.cover_image`. The file lives on the `public`
 * disk (managed by CoverImageService); this column only holds its path. No FK — a
 * cover is a single file, not a tracked media row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });
    }
};
