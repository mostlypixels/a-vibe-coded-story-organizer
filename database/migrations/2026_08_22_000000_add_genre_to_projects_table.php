<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `projects.genre` — stored label for the onboarding genre bundle, see
 * App\Enums\Genre. Nullable: existing projects, and projects seeded with
 * `Genre::Blank`, have no bundle content driven by this value in v1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('genre')->nullable()->after('total_word_goal');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('genre');
        });
    }
};
