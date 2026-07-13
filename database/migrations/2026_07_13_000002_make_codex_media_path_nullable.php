<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Importing a metadata-only export archive (its "Include images & files"
     * toggle was off) creates codex_media rows that faithfully record every
     * media file's metadata while no bytes were ever shipped — such a row has
     * no stored file, so `path` becomes nullable. A null path means "file not
     * included in this import" (see .specs → import → open-questions.md,
     * question 2); every other creation path still always stores a file.
     */
    public function up(): void
    {
        Schema::table('codex_media', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('codex_media', function (Blueprint $table) {
            $table->string('path')->nullable(false)->change();
        });
    }
};
