<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Run mode now comes from config('import.run_in_background'): an env var
    // cannot reach an existing row, so the column could not follow the runtime.
    public function up(): void
    {
        Schema::table('import_settings', function (Blueprint $table) {
            $table->dropColumn('run_in_background');
        });
    }

    public function down(): void
    {
        Schema::table('import_settings', function (Blueprint $table) {
            $table->boolean('run_in_background')->default(false);
        });
    }
};
