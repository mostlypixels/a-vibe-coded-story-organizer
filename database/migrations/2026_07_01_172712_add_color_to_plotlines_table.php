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
        Schema::table('plotlines', function (Blueprint $table) {
            $table->string('color', 7);
            $table->unique(['project_id', 'color']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plotlines', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'color']);
            $table->dropColumn('color');
        });
    }
};
