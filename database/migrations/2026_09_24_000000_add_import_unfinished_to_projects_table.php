<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `projects.import_unfinished` — the project comes from an import that did not
 * finish. `imports:purge` deletes the import row after the retention window, so
 * the project must keep this fact itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('import_unfinished')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('import_unfinished');
        });
    }
};
